import { loadStripe, Stripe } from "@stripe/stripe-js";
import {
  FormEvent,
  useMemo,
  useRef,
  useState,
} from "react";

export type StripeTokenizedCheckoutStatus =
  | "idle"
  | "ready"
  | "processing"
  | "authenticating"
  | "pending"
  | "success"
  | "error";

type MetadataValue =
  | string
  | number
  | boolean
  | null;

type Metadata = Record<
  string,
  MetadataValue
>;

export type StripeTokenizedPaymentResponse = {
  ok: boolean;
  message?: string;

  payment_id?: string | number;
  payment_intent_id?: string;

  status?: string;
  provider_status?: string;

  next_action?: unknown;

  payment?: {
    id?: string | number;
    status?: string;
    provider_status?: string;

    metadata?: Record<string, unknown>;

    references?: {
      provider_payment_id?: string | null;
    } | null;

    next_action?: unknown;
  };

  errors?: unknown;
};

type StripeConfirmResponse = {
  ok: boolean;
  message?: string;
  payment?: unknown;
  errors?: unknown;
};

type Props = {
  publicKey: string;

  amount: number;
  currency: string;
  externalReference: string;

  customerId: string;
  paymentMethodId: string;

  payerReference?: string;
  payerEmail?: string;
  description?: string;

  offSession?: boolean;

  processUrl: string;
  confirmIntentUrl?: string;

  csrfToken?: string;
  returnUrl?: string;

  metadata?: Metadata;
  buttonText?: string;

  disabled?: boolean;

  onStatusChange?: (
    status: StripeTokenizedCheckoutStatus,
    message?: string,
  ) => void;

  onSuccess?: (
    response:
      | StripeTokenizedPaymentResponse
      | StripeConfirmResponse,
  ) => void | Promise<void>;

  onError?: (error: unknown) => void;
};

async function parseJsonResponse(
  response: Response,
): Promise<any> {
  const text = await response.text();

  try {
    return text
      ? JSON.parse(text)
      : null;
  } catch {
    throw new Error(
      `El backend no devolvió JSON. Status ${response.status}. Respuesta: ${text.substring(
        0,
        300,
      )}`,
    );
  }
}

function getBackendErrorMessage(
  data: any,
  fallback: string,
): string {
  return (
    data?.message ||
    data?.error ||
    data?.errors?.message ||
    data?.errors?.[0] ||
    fallback
  );
}

function getErrorMessage(
  error: unknown,
  fallback: string,
): string {
  if (
    error instanceof Error &&
    error.message.trim() !== ""
  ) {
    return error.message;
  }

  if (
    error &&
    typeof error === "object"
  ) {
    const candidate = error as {
      message?: unknown;
    };

    if (
      typeof candidate.message ===
        "string" &&
      candidate.message.trim() !== ""
    ) {
      return candidate.message;
    }
  }

  return fallback;
}

async function postJson<T>(
  url: string,
  payload: unknown,
  csrfToken?: string,
): Promise<T> {
  const response = await fetch(url, {
    method: "POST",

    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",

      ...(csrfToken
        ? {
            "X-CSRF-TOKEN": csrfToken,
          }
        : {}),
    },

    body: JSON.stringify(payload),
  });

  const data =
    await parseJsonResponse(response);

  if (
    !response.ok ||
    data?.ok === false
  ) {
    throw new Error(
      getBackendErrorMessage(
        data,
        "No se pudo procesar el pago.",
      ),
    );
  }

  return data as T;
}

function makeIdempotencyKey(
  prefix: string,
): string {
  if (
    typeof crypto !== "undefined" &&
    "randomUUID" in crypto
  ) {
    return `${prefix}-${crypto.randomUUID()}`.slice(
      0,
      64,
    );
  }

  return `${prefix}-${Date.now()}-${Math.random()
    .toString(16)
    .slice(2)}`.slice(0, 64);
}

function resolveProviderStatus(
  response: StripeTokenizedPaymentResponse,
): string {
  return String(
    response.provider_status ??
      response.payment?.provider_status ??
      response.status ??
      response.payment?.status ??
      "",
  ).toLowerCase();
}

function resolveClientSecret(
  response: StripeTokenizedPaymentResponse,
): string | null {
  const metadata =
    response.payment?.metadata ?? {};

  const clientSecret =
    metadata.stripe_client_secret ??
    metadata.client_secret ??
    null;

  if (
    typeof clientSecret !== "string" ||
    clientSecret.trim() === ""
  ) {
    return null;
  }

  return clientSecret;
}

function resolvePaymentIntentId(
  response: StripeTokenizedPaymentResponse,
): string | null {
  const metadata =
    response.payment?.metadata ?? {};

  const metadataPaymentIntentId =
    metadata.stripe_payment_intent_id;

  if (
    typeof response.payment_intent_id ===
      "string" &&
    response.payment_intent_id !== ""
  ) {
    return response.payment_intent_id;
  }

  const referenceId =
    response.payment?.references
      ?.provider_payment_id;

  if (
    typeof referenceId === "string" &&
    referenceId !== ""
  ) {
    return referenceId;
  }

  if (
    typeof metadataPaymentIntentId ===
      "string" &&
    metadataPaymentIntentId !== ""
  ) {
    return metadataPaymentIntentId;
  }

  return null;
}

function resolveLocalPaymentId(
  response: StripeTokenizedPaymentResponse,
): string | number | null {
  return (
    response.payment_id ??
    response.payment?.id ??
    null
  );
}

function requiresCustomerAction(
  response: StripeTokenizedPaymentResponse,
): boolean {
  const status =
    resolveProviderStatus(response);

  return (
    status === "requires_action" ||
    status ===
      "requires_source_action" ||
    Boolean(
      response.next_action ||
        response.payment?.next_action,
    )
  );
}

function isSuccessfulStatus(
  status: string,
): boolean {
  return [
    "succeeded",
    "approved",
    "completed",
  ].includes(status);
}

function isPendingStatus(
  status: string,
): boolean {
  return [
    "pending",
    "processing",
    "requires_capture",
  ].includes(status);
}

export function StripeTokenizedCardCheckout({
  publicKey,
  amount,
  currency,
  externalReference,
  customerId,
  paymentMethodId,
  payerReference,
  payerEmail,
  description =
    "Pago con tarjeta guardada",
  offSession = false,
  processUrl,
  confirmIntentUrl,
  csrfToken,
  returnUrl,
  metadata = {},
  buttonText = "Pagar",
  disabled = false,
  onStatusChange,
  onSuccess,
  onError,
}: Props) {
  const [status, setStatus] =
    useState<StripeTokenizedCheckoutStatus>(
      "ready",
    );

  const [message, setMessage] =
    useState("");

  const lockRef = useRef(false);

  const stripePromise = useMemo<
    Promise<Stripe | null> | null
  >(() => {
    if (!publicKey) {
      return null;
    }

    return loadStripe(publicKey);
  }, [publicKey]);

  function changeStatus(
    nextStatus:
      StripeTokenizedCheckoutStatus,
    nextMessage = "",
  ): void {
    setStatus(nextStatus);
    setMessage(nextMessage);

    onStatusChange?.(
      nextStatus,
      nextMessage,
    );
  }

  async function synchronizePayment(
    initialResponse:
      StripeTokenizedPaymentResponse,
    paymentIntentId: string,
    stripeStatus?: string,
  ): Promise<
    | StripeTokenizedPaymentResponse
    | StripeConfirmResponse
  > {
    const localPaymentId =
      resolveLocalPaymentId(
        initialResponse,
      );

    if (
      !confirmIntentUrl ||
      !localPaymentId
    ) {
      return initialResponse;
    }

    return postJson<StripeConfirmResponse>(
      confirmIntentUrl,
      {
        payment_id: localPaymentId,

        provider_payment_id:
          paymentIntentId,

        stripe_status:
          stripeStatus ?? null,

        idempotency_key:
          makeIdempotencyKey(
            "stripe-tokenized-confirm",
          ),
      },
      csrfToken,
    );
  }

  async function completeAuthentication(
    response:
      StripeTokenizedPaymentResponse,
  ): Promise<
    | StripeTokenizedPaymentResponse
    | StripeConfirmResponse
  > {
    if (offSession) {
      throw new Error(
        "El banco requiere autenticación del cliente. Este pago no puede finalizarse automáticamente como off-session.",
      );
    }

    if (!stripePromise) {
      throw new Error(
        "Falta configurar la llave pública de Stripe.",
      );
    }

    const clientSecret =
      resolveClientSecret(response);

    const paymentIntentId =
      resolvePaymentIntentId(response);

    if (!clientSecret) {
      throw new Error(
        "Stripe solicitó autenticación adicional, pero no regresó client_secret.",
      );
    }

    if (!paymentIntentId) {
      throw new Error(
        "Stripe no regresó payment_intent_id.",
      );
    }

    changeStatus(
      "authenticating",
      "El banco requiere una verificación adicional.",
    );

    const stripe =
      await stripePromise;

    if (!stripe) {
      throw new Error(
        "No se pudo inicializar Stripe.",
      );
    }

    const result =
      await stripe.handleNextAction({
        clientSecret,
      });

    if (result.error) {
      throw new Error(
        result.error.message ||
          "No se pudo completar la autenticación bancaria.",
      );
    }

    if (!result.paymentIntent) {
      throw new Error(
        "Stripe no regresó el PaymentIntent autenticado.",
      );
    }

    return synchronizePayment(
      response,
      result.paymentIntent.id,
      result.paymentIntent.status,
    );
  }

  async function handleSubmit(
    event: FormEvent<HTMLFormElement>,
  ): Promise<void> {
    event.preventDefault();

    if (
      lockRef.current ||
      disabled
    ) {
      return;
    }

    if (!processUrl) {
      changeStatus(
        "error",
        "Falta configurar processUrl.",
      );

      return;
    }

    if (
      !customerId.startsWith("cus_")
    ) {
      changeStatus(
        "error",
        "El customerId de Stripe no es válido.",
      );

      return;
    }

    if (
      !paymentMethodId.startsWith("pm_")
    ) {
      changeStatus(
        "error",
        "El paymentMethodId de Stripe no es válido.",
      );

      return;
    }

    if (
      !Number.isFinite(Number(amount)) ||
      Number(amount) <= 0
    ) {
      changeStatus(
        "error",
        "El monto no es válido.",
      );

      return;
    }

    lockRef.current = true;

    changeStatus(
      "processing",
      "Procesando pago...",
    );

    try {
      const response =
        await postJson<StripeTokenizedPaymentResponse>(
          processUrl,
          {
            amount,
            currency,

            external_reference:
              externalReference,

            payer_reference:
              payerReference ?? null,

            payer_email:
              payerEmail ?? null,

            customer_id:
              customerId,

            payment_method_id:
              paymentMethodId,

            off_session:
              offSession,

            description,

            return_url:
              returnUrl ?? null,

            idempotency_key:
              makeIdempotencyKey(
                "stripe-tokenized-payment",
              ),

            metadata,
          },
          csrfToken,
        );

      let finalResponse:
        | StripeTokenizedPaymentResponse
        | StripeConfirmResponse =
        response;

      if (
        requiresCustomerAction(response)
      ) {
        finalResponse =
          await completeAuthentication(
            response,
          );
      } else {
        const providerStatus =
          resolveProviderStatus(response);

        if (
          isPendingStatus(
            providerStatus,
          )
        ) {
          changeStatus(
            "pending",
            "El pago está siendo procesado.",
          );

          await onSuccess?.(
            finalResponse,
          );

          return;
        }

        if (
          providerStatus &&
          !isSuccessfulStatus(
            providerStatus,
          )
        ) {
          throw new Error(
            `Stripe no aprobó el pago. Estado: ${providerStatus}.`,
          );
        }
      }

      changeStatus(
        "success",
        "Pago procesado correctamente.",
      );

      await onSuccess?.(
        finalResponse,
      );
    } catch (error) {
      const errorMessage =
        getErrorMessage(
          error,
          "No se pudo completar el pago.",
        );

      changeStatus(
        "error",
        errorMessage,
      );

      onError?.(error);
    } finally {
      lockRef.current = false;
    }
  }

  const processing =
    status === "processing" ||
    status === "authenticating";

  return (
    <form onSubmit={handleSubmit}>
      {message && (
        <p role="status">
          {message}
        </p>
      )}

      <button
        type="submit"
        disabled={
          disabled ||
          processing
        }
      >
        {status === "processing"
          ? "Procesando..."
          : status ===
              "authenticating"
            ? "Verificando..."
            : buttonText}
      </button>
    </form>
  );
}