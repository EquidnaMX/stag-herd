import {
  CardElement,
  Elements,
  useElements,
  useStripe,
} from "@stripe/react-stripe-js";
import { loadStripe, type Stripe } from "@stripe/stripe-js";
import {
  type CSSProperties,
  type FormEvent,
  useMemo,
  useRef,
  useState,
} from "react";
import { Lock } from "lucide-react";

export type StripeCardCheckoutStatus =
  | "idle"
  | "loading"
  | "ready"
  | "processing"
  | "success"
  | "error";

type MetadataValue = string | number | boolean | null;

type Metadata = Record<string, MetadataValue>;

export type StripeCheckoutMetadata = Record<string, MetadataValue>;

type StripeIntentResponse = {
  ok: boolean;
  message?: string;
  client_secret?: string;
  payment_intent_id?: string;
  payment_id?: string | number;
  payment?: {
    id?: string | number;
    metadata?: Record<string, unknown>;
    references?: {
      provider_payment_id?: string | null;
    } | null;
  };
  errors?: unknown;
};

export type StripeConfirmResponse = {
  ok: boolean;
  message?: string;
  payment_intent_id?: string;
  provider_payment_id?: string;
  status?: string | null;
  provider_status?: string | null;
  payment?: unknown;
  errors?: unknown;
};

type Props = {
  publicKey: string;
  amount: number;
  currency: string;
  externalReference: string;

  payerReference?: string;
  payerEmail?: string;

  metadata?: Metadata;
  existingCustomerId?: string | null;
  description?: string;

  createIntentUrl: string;
  confirmIntentUrl: string;
  csrfToken?: string;
  returnUrl?: string;

  className?: string;
  containerStyle?: CSSProperties;

  onStatusChange?: (status: StripeCardCheckoutStatus, message?: string) => void;

  onSuccess?: (payload: StripeConfirmResponse) => void | Promise<void>;

  onError?: (error: unknown) => void;
};

function readMetadataFromPage(): StripeCheckoutMetadata {
  if (typeof document === "undefined") {
    return {};
  }

  const metadata: StripeCheckoutMetadata = {};

  const elements = document.querySelectorAll<
    HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement
  >("[data-stag-herd-metadata]");

  elements.forEach((element) => {
    const key = element.dataset.stagHerdMetadata?.trim();

    if (!key) {
      return;
    }

    if (
      element instanceof HTMLInputElement &&
      (element.type === "checkbox" || element.type === "radio")
    ) {
      if (!element.checked) {
        return;
      }

      metadata[key] = element.value?.trim() || true;

      return;
    }

    const value = element.value?.trim();

    if (!value) {
      return;
    }

    metadata[key] = value;
  });

  return metadata;
}

function mergeMetadata(
  pageMetadata: StripeCheckoutMetadata,
  providedMetadata: StripeCheckoutMetadata = {},
): StripeCheckoutMetadata {
  return {
    ...pageMetadata,
    ...providedMetadata,
  };
}

async function parseJsonResponse(response: Response): Promise<unknown> {
  const text = await response.text();

  if (!text.trim()) {
    return null;
  }

  try {
    return JSON.parse(text);
  } catch {
    throw new Error(
      [
        "El backend no devolvió JSON.",
        `Status ${response.status}.`,
        `Respuesta: ${text.substring(0, 300)}`,
      ].join(" "),
    );
  }
}

async function postJson<T>(
  url: string,
  payload: unknown,
  csrfToken?: string,
): Promise<T> {
  const response = await fetch(url, {
    method: "POST",
    credentials: "same-origin",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      "X-Requested-With": "XMLHttpRequest",
      ...(csrfToken
        ? {
            "X-CSRF-TOKEN": csrfToken,
          }
        : {}),
    },
    body: JSON.stringify(payload),
  });

  const data = await parseJsonResponse(response);

  if (
    !response.ok ||
    (data && typeof data === "object" && "ok" in data && data.ok === false)
  ) {
    throw new Error(
      getBackendErrorMessage(data, "No se pudo procesar el pago."),
    );
  }

  return data as T;
}

function getBackendErrorMessage(data: unknown, fallback: string): string {
  if (!data || typeof data !== "object") {
    return fallback;
  }

  const response = data as {
    message?: unknown;
    error?: unknown;
    errors?: unknown;
  };

  if (typeof response.message === "string" && response.message.trim()) {
    return response.message;
  }

  if (typeof response.error === "string" && response.error.trim()) {
    return response.error;
  }

  if (Array.isArray(response.errors)) {
    const messages = response.errors
      .map((error) => {
        if (typeof error === "string") {
          return error;
        }

        if (
          error &&
          typeof error === "object" &&
          "message" in error &&
          typeof error.message === "string"
        ) {
          return error.message;
        }

        return null;
      })
      .filter((message): message is string => Boolean(message));

    if (messages.length > 0) {
      return messages.join("\n");
    }
  }

  if (
    response.errors &&
    typeof response.errors === "object" &&
    "message" in response.errors &&
    typeof response.errors.message === "string"
  ) {
    return response.errors.message;
  }

  if (response.errors && typeof response.errors === "object") {
    const validationMessages = Object.values(response.errors).flatMap(
      (value) => {
        if (Array.isArray(value)) {
          return value.filter(
            (item): item is string => typeof item === "string",
          );
        }

        return typeof value === "string" ? [value] : [];
      },
    );

    if (validationMessages.length > 0) {
      return validationMessages.join("\n");
    }
  }

  return fallback;
}

function getErrorMessage(error: unknown, fallback: string): string {
  if (error instanceof Error && error.message.trim()) {
    return error.message;
  }

  if (error && typeof error === "object" && "message" in error) {
    const message = error.message;

    if (typeof message === "string" && message.trim()) {
      return message;
    }
  }

  return fallback;
}

function makeIdempotencyKey(prefix: string): string {
  if (typeof crypto !== "undefined" && "randomUUID" in crypto) {
    return `${prefix}-${crypto.randomUUID()}`.slice(0, 64);
  }

  return `${prefix}-${Date.now()}-${Math.random().toString(16).slice(2)}`.slice(
    0,
    64,
  );
}

function resolveClientSecret(response: StripeIntentResponse): string | null {
  const clientSecret =
    response.client_secret ??
    response.payment?.metadata?.stripe_client_secret ??
    response.payment?.metadata?.client_secret;

  if (typeof clientSecret !== "string" || !clientSecret.trim()) {
    return null;
  }

  return clientSecret;
}

function resolvePaymentIntentId(response: StripeIntentResponse): string | null {
  const paymentIntentId =
    response.payment_intent_id ??
    response.payment?.references?.provider_payment_id ??
    response.payment?.metadata?.stripe_payment_intent_id;

  if (typeof paymentIntentId !== "string" || !paymentIntentId.trim()) {
    return null;
  }

  return paymentIntentId;
}

export function StripeCardCheckout({
  publicKey,
  amount,
  currency,
  externalReference,
  payerReference,
  payerEmail,
  metadata = {},
  existingCustomerId = null,
  description,
  createIntentUrl,
  confirmIntentUrl,
  csrfToken,
  returnUrl,
  className,
  containerStyle,
  onStatusChange,
  onSuccess,
  onError,
}: Props) {
  const [status, setStatus] = useState<StripeCardCheckoutStatus>("idle");
  const [message, setMessage] = useState("");

  const stripePromise = useMemo<Promise<Stripe | null> | null>(() => {
    if (!publicKey.trim()) {
      return null;
    }

    return loadStripe(publicKey);
  }, [publicKey]);

  const metadataKey = useMemo(() => JSON.stringify(metadata), [metadata]);

  function changeStatus(
    nextStatus: StripeCardCheckoutStatus,
    nextMessage = "",
  ): void {
    setStatus(nextStatus);
    setMessage(nextMessage);

    onStatusChange?.(nextStatus, nextMessage);
  }

  if (!stripePromise) {
    return (
      <div className={className} style={containerStyle}>
        <div className="stag-herd-stripe-error">
          Falta configurar la llave pública de Stripe.
        </div>
      </div>
    );
  }

  return (
    <div className={className} style={containerStyle}>
      <Elements stripe={stripePromise}>
        <StripeCardForm
          amount={amount}
          currency={currency}
          externalReference={externalReference}
          payerReference={payerReference}
          payerEmail={payerEmail}
          description={description}
          metadata={JSON.parse(metadataKey || "{}")}
          existingCustomerId={existingCustomerId}
          createIntentUrl={createIntentUrl}
          confirmIntentUrl={confirmIntentUrl}
          csrfToken={csrfToken}
          returnUrl={returnUrl}
          status={status}
          message={message}
          onStatusChange={changeStatus}
          onSuccess={onSuccess}
          onError={onError}
        />
      </Elements>
    </div>
  );
}

function StripeCardForm({
  amount,
  currency,
  externalReference,
  payerReference,
  payerEmail,
  description,
  metadata,
  existingCustomerId,
  createIntentUrl,
  confirmIntentUrl,
  csrfToken,
  returnUrl,
  status,
  message,
  onStatusChange,
  onSuccess,
  onError,
}: {
  amount: number;
  currency: string;
  externalReference: string;
  payerReference?: string;
  payerEmail?: string;
  description?: string;
  metadata: StripeCheckoutMetadata;
  existingCustomerId?: string | null;
  createIntentUrl: string;
  confirmIntentUrl: string;
  csrfToken?: string;
  returnUrl?: string;
  status: StripeCardCheckoutStatus;
  message: string;

  onStatusChange?: (status: StripeCardCheckoutStatus, message?: string) => void;

  onSuccess?: (payload: StripeConfirmResponse) => void | Promise<void>;

  onError?: (error: unknown) => void;
}) {
  const stripe = useStripe();
  const elements = useElements();

  const [ready, setReady] = useState(false);
  const [processing, setProcessing] = useState(false);

  const lockRef = useRef(false);

  function changeStatus(
    nextStatus: StripeCardCheckoutStatus,
    nextMessage = "",
  ): void {
    onStatusChange?.(nextStatus, nextMessage);
  }

  async function handleSubmit(
    event: FormEvent<HTMLFormElement>,
  ): Promise<void> {
    event.preventDefault();

    if (lockRef.current || processing) {
      return;
    }

    if (!stripe || !elements) {
      changeStatus("error", "Stripe todavía no está listo.");
      return;
    }

    if (!ready) {
      changeStatus("error", "Espera a que cargue el formulario de Stripe.");
      return;
    }

    if (!createIntentUrl.trim()) {
      changeStatus(
        "error",
        "Falta configurar la URL para crear el PaymentIntent.",
      );
      return;
    }

    const numericAmount = Number(amount);

    if (!Number.isFinite(numericAmount) || numericAmount <= 0) {
      changeStatus("error", "El monto no es válido.");
      return;
    }

    if (!currency.trim()) {
      changeStatus("error", "La moneda es obligatoria.");
      return;
    }

    const cardElement = elements.getElement(CardElement);

    if (!cardElement) {
      changeStatus("error", "No se pudo cargar el campo de tarjeta.");
      return;
    }

    lockRef.current = true;
    setProcessing(true);

    try {
      changeStatus("loading", "Preparando pago seguro con Stripe...");

      const mergedMetadata = mergeMetadata(readMetadataFromPage(), metadata);

      const shouldSavePaymentMethod =
        mergedMetadata.save_payment_method === true ||
        mergedMetadata.save_payment_method === "true" ||
        mergedMetadata.save_payment_method === 1 ||
        mergedMetadata.save_payment_method === "1";

      const normalizedExistingCustomerId =
        typeof existingCustomerId === "string" &&
        existingCustomerId.trim() !== ""
          ? existingCustomerId.trim()
          : null;

      const intentPayload: Record<string, unknown> = {
        amount: numericAmount,
        currency: currency.toUpperCase(),
        external_reference: externalReference || undefined,
        payer_reference: payerReference || undefined,
        payer_email: payerEmail || undefined,
        description: description || undefined,
        idempotency_key: makeIdempotencyKey("stripe-intent"),
        metadata: mergedMetadata,
      };

      if (shouldSavePaymentMethod && normalizedExistingCustomerId) {
        intentPayload.stripe = {
          customer: normalizedExistingCustomerId,
        };
      }

      const intentResponse = await postJson<StripeIntentResponse>(
        createIntentUrl,
        intentPayload,
        csrfToken,
      );

      const clientSecret = resolveClientSecret(intentResponse);
      const paymentIntentId = resolvePaymentIntentId(intentResponse);

      if (!clientSecret) {
        throw new Error("Stripe no regresó client_secret.");
      }

      if (!paymentIntentId) {
        throw new Error("Stripe no regresó payment_intent_id.");
      }

      changeStatus("processing", "Procesando pago...");

      const cardholderName =
        typeof mergedMetadata.card_display_name === "string"
          ? mergedMetadata.card_display_name.trim()
          : "";

      const confirmResult = await stripe.confirmCardPayment(clientSecret, {
        payment_method: {
          card: cardElement,
          billing_details: {
            ...(payerEmail
              ? {
                  email: payerEmail,
                }
              : {}),
            ...(cardholderName
              ? {
                  name: cardholderName,
                }
              : {}),
          },
        },
      });

      if (confirmResult.error) {
        throw new Error(
          confirmResult.error.message ||
            "No se pudo confirmar el pago con Stripe.",
        );
      }

      const paymentIntent = confirmResult.paymentIntent;

      if (!paymentIntent?.id) {
        throw new Error("Stripe no regresó el PaymentIntent confirmado.");
      }

      const confirmResponse = await postJson<StripeConfirmResponse>(
        confirmIntentUrl,
        {
          provider_payment_id: paymentIntent.id,
          stripe_status: paymentIntent.status ?? null,
          payer_email: payerEmail ?? undefined,
          external_reference: externalReference || undefined,
          payer_reference: payerReference || undefined,
          description: description || undefined,
          metadata,
        },
        csrfToken,
      );

      changeStatus("success", "Pago confirmado correctamente.");

      await onSuccess?.(confirmResponse);
    } catch (error) {
      const errorMessage = getErrorMessage(
        error,
        "No se pudo completar el pago con Stripe.",
      );

      changeStatus("error", errorMessage);
      onError?.(error);
    } finally {
      lockRef.current = false;
      setProcessing(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} className="stag-herd-stripe-form">
      <div className="flex items-start justify-between gap-4">
        <div>
          <h3 className="text-base font-semibold text-slate-900">
            Información de pago
          </h3>

          <p className="mt-1 text-sm text-slate-500">
            Ingresa los datos de tu tarjeta.
          </p>
        </div>

        <div className="rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-medium text-emerald-700">
          Pago seguro
        </div>
      </div>

      <div className="mt-6">
        <label className="mb-2 block text-sm font-medium text-slate-700">
          Datos de la tarjeta
        </label>

        <div
          className={[
            "rounded-xl bg-slate-50 px-4 py-4 transition-all duration-200",
            status === "error"
              ? "bg-red-50 ring-2 ring-red-100"
              : "focus-within:bg-white focus-within:ring-2 focus-within:ring-primary/10",
          ].join(" ")}
        >
          <CardElement
            options={{
              hidePostalCode: true,
              disableLink: true,
              style: {
                base: {
                  fontSize: "16px",
                  color: "#0f172a",
                  fontFamily: "Inter, ui-sans-serif, system-ui, sans-serif",
                  fontSmoothing: "antialiased",
                  "::placeholder": {
                    color: "#94a3b8",
                  },
                },
                invalid: {
                  color: "#dc2626",
                  iconColor: "#dc2626",
                },
              },
            }}
            onReady={() => {
              setReady(true);
              changeStatus("ready");
            }}
            onChange={(event) => {
              if (event.error?.message) {
                changeStatus("error", event.error.message);
                return;
              }

              if (event.complete) {
                changeStatus("ready");
                return;
              }

              if (status === "error") {
                changeStatus("ready");
              }
            }}
          />
        </div>

        {message && (
          <div
            role="alert"
            className="mt-3 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700"
          >
            {message}
          </div>
        )}

        <button
          type="submit"
          disabled={!stripe || !elements || !ready || processing}
          className="mt-5 flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-5 py-3.5 text-sm font-semibold text-white shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md hover:brightness-95 active:translate-y-0 disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:translate-y-0 disabled:hover:shadow-sm"
        >
          {processing ? (
            "Procesando pago..."
          ) : (
            <>
              <Lock className="h-4 w-4" />
              Pagar con tarjeta
            </>
          )}
        </button>
      </div>
    </form>
  );
}
