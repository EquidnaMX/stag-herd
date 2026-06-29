import {
  Elements,
  PaymentElement,
  useElements,
  useStripe,
} from "@stripe/react-stripe-js";
import { loadStripe, Stripe } from "@stripe/stripe-js";
import {
  CSSProperties,
  FormEvent,
  useEffect,
  useMemo,
  useRef,
  useState,
} from "react";

type CheckoutStatus =
  | "idle"
  | "loading"
  | "ready"
  | "processing"
  | "success"
  | "error";

type MetadataValue = string | number | boolean | null;
type Metadata = Record<string, MetadataValue>;

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
  payerEmail?: string;
  description?: string;

  createIntentUrl: string;
  confirmIntentUrl: string;
  csrfToken?: string;

  returnUrl?: string;

  className?: string;
  containerStyle?: CSSProperties;

  onStatusChange?: (status: CheckoutStatus, message?: string) => void;
  onSuccess?: (payload: StripeConfirmResponse) => void | Promise<void>;
  onError?: (error: unknown) => void;
};

function readMetadataFromPage(): Metadata {
  const metadata: Metadata = {};

  const elements = document.querySelectorAll<
    HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement
  >("[data-stag-herd-metadata]");

  elements.forEach((element) => {
    const key = element.dataset.stagHerdMetadata;

    if (!key) {
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

async function parseJsonResponse(response: Response): Promise<any> {
  const text = await response.text();

  try {
    return text ? JSON.parse(text) : null;
  } catch {
    throw new Error(
      `El backend no devolvió JSON. Status ${response.status}. Respuesta: ${text.substring(
        0,
        300,
      )}`,
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
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...(csrfToken ? { "X-CSRF-TOKEN": csrfToken } : {}),
    },
    body: JSON.stringify(payload),
  });

  const data = await parseJsonResponse(response);

  if (!response.ok || data?.ok === false) {
    throw new Error(
      getBackendErrorMessage(data, "No se pudo procesar el pago."),
    );
  }

  return data as T;
}

function getBackendErrorMessage(data: any, fallback: string): string {
  return (
    data?.message ||
    data?.errors?.[0] ||
    data?.error ||
    data?.errors?.message ||
    fallback
  );
}

function getErrorMessage(error: unknown, fallback: string): string {
  if (error instanceof Error && error.message.trim() !== "") {
    return error.message;
  }

  if (error && typeof error === "object") {
    const e = error as { message?: unknown };

    if (typeof e.message === "string" && e.message.trim() !== "") {
      return e.message;
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
  return (
    response.client_secret ||
    String(response.payment?.metadata?.stripe_client_secret ?? "") ||
    String(response.payment?.metadata?.client_secret ?? "") ||
    null
  );
}

function resolvePaymentIntentId(response: StripeIntentResponse): string | null {
  return (
    response.payment_intent_id ||
    response.payment?.references?.provider_payment_id ||
    String(response.payment?.metadata?.stripe_payment_intent_id ?? "") ||
    null
  );
}

function resolveLocalPaymentId(
  response: StripeIntentResponse,
): string | number | null {
  return response.payment_id ?? response.payment?.id ?? null;
}

export function StripeCardCheckout({
  publicKey,
  amount,
  currency,
  externalReference,
  payerEmail,
  description = "Pago desde Stripe Payment Element",
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
  const [clientSecret, setClientSecret] = useState<string | null>(null);
  const [paymentIntentId, setPaymentIntentId] = useState<string | null>(null);
  const [localPaymentId, setLocalPaymentId] = useState<string | number | null>(
    null,
  );
  const [status, setStatus] = useState<CheckoutStatus>("idle");
  const [message, setMessage] = useState<string>("");

  const stripePromise = useMemo<Promise<Stripe | null> | null>(() => {
    if (!publicKey) {
      return null;
    }

    return loadStripe(publicKey);
  }, [publicKey]);

  const intentKey = useMemo(() => {
    return JSON.stringify({
      amount,
      currency,
      externalReference,
      payerEmail,
    });
  }, [amount, currency, externalReference, payerEmail]);

  const statusRef = useRef<CheckoutStatus>("idle");

  function changeStatus(nextStatus: CheckoutStatus, nextMessage = "") {
    statusRef.current = nextStatus;
    setStatus(nextStatus);
    setMessage(nextMessage);
    onStatusChange?.(nextStatus, nextMessage);
  }

  useEffect(() => {
    let cancelled = false;

    async function createIntent() {
      if (!publicKey) {
        changeStatus("error", "Falta configurar la llave pública de Stripe.");
        return;
      }

      if (!createIntentUrl) {
        changeStatus(
          "error",
          "Falta configurar la URL para crear el PaymentIntent.",
        );
        return;
      }

      if (!amount || Number(amount) <= 0) {
        changeStatus("error", "El monto no es válido.");
        return;
      }

      try {
        changeStatus("loading", "Preparando pago seguro con Stripe...");

        const metadata = readMetadataFromPage();

        const response = await postJson<StripeIntentResponse>(
          createIntentUrl,
          {
            amount,
            currency,
            external_reference: externalReference,
            payer_email: payerEmail,
            description,
            idempotency_key: makeIdempotencyKey("stripe-intent"),
            metadata,
          },
          csrfToken,
        );

        const nextClientSecret = resolveClientSecret(response);
        const nextPaymentIntentId = resolvePaymentIntentId(response);
        const nextLocalPaymentId = resolveLocalPaymentId(response);

        if (!nextClientSecret) {
          throw new Error("Stripe no regresó client_secret.");
        }

        if (!nextPaymentIntentId) {
          throw new Error("Stripe no regresó payment_intent_id.");
        }

        if (!nextLocalPaymentId) {
          throw new Error("Stag Herd no regresó payment_id local.");
        }

        if (!cancelled) {
          setClientSecret(nextClientSecret);
          setPaymentIntentId(nextPaymentIntentId);
          setLocalPaymentId(nextLocalPaymentId);
          changeStatus("ready");
        }
      } catch (error) {
        if (!cancelled) {
          const errorMessage = getErrorMessage(
            error,
            "No se pudo preparar el pago con Stripe.",
          );

          changeStatus("error", errorMessage);
          onError?.(error);
        }
      }
    }

    setClientSecret(null);
    setPaymentIntentId(null);
    setLocalPaymentId(null);

    createIntent();

    return () => {
      cancelled = true;
    };
  }, [
    intentKey,
    publicKey,
    createIntentUrl,
    amount,
    currency,
    externalReference,
    payerEmail,
    description,
    csrfToken,
  ]);

  const options = useMemo(() => {
    if (!clientSecret) {
      return undefined;
    }

    return {
      clientSecret,
      appearance: {
        theme: "stripe" as const,
        variables: {
          borderRadius: "10px",
          fontFamily: "Inter, system-ui, sans-serif",
        },
      },
    };
  }, [clientSecret]);

  if (status === "loading") {
    return (
      <div className={className} style={containerStyle}>
        <div className="stag-herd-stripe-message">
          {message || "Preparando pago seguro..."}
        </div>
      </div>
    );
  }

  if (status === "error") {
    return (
      <div className={className} style={containerStyle}>
        <div className="stag-herd-stripe-error">
          {message || "No se pudo cargar Stripe."}
        </div>
      </div>
    );
  }

  if (
    !stripePromise ||
    !clientSecret ||
    !paymentIntentId ||
    !localPaymentId ||
    !options
  ) {
    return null;
  }

  return (
    <div className={className} style={containerStyle}>
      <Elements key={clientSecret} stripe={stripePromise} options={options}>
        <StripeCardForm
          confirmIntentUrl={confirmIntentUrl}
          csrfToken={csrfToken}
          payerEmail={payerEmail}
          returnUrl={returnUrl}
          paymentIntentId={paymentIntentId}
          localPaymentId={localPaymentId}
          onStatusChange={onStatusChange}
          onSuccess={onSuccess}
          onError={onError}
        />
      </Elements>
    </div>
  );
}

function StripeCardForm({
  confirmIntentUrl,
  csrfToken,
  payerEmail,
  returnUrl,
  paymentIntentId,
  localPaymentId,
  onStatusChange,
  onSuccess,
  onError,
}: {
  confirmIntentUrl: string;
  csrfToken?: string;
  payerEmail?: string;
  returnUrl?: string;
  paymentIntentId: string;
  localPaymentId: string | number;
  onStatusChange?: (status: CheckoutStatus, message?: string) => void;
  onSuccess?: (payload: StripeConfirmResponse) => void | Promise<void>;
  onError?: (error: unknown) => void;
}) {
  const stripe = useStripe();
  const elements = useElements();

  const [ready, setReady] = useState(false);
  const [processing, setProcessing] = useState(false);
  const [message, setMessage] = useState("");

  const lockRef = useRef(false);

  function changeStatus(status: CheckoutStatus, nextMessage = "") {
    setMessage(nextMessage);
    onStatusChange?.(status, nextMessage);
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
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

    if (!confirmIntentUrl) {
      changeStatus("error", "Falta configurar la URL para confirmar el pago.");
      return;
    }

    lockRef.current = true;
    setProcessing(true);
    changeStatus("processing", "Procesando pago...");

    try {
      const result = await stripe.confirmPayment({
        elements,
        confirmParams: {
          receipt_email: payerEmail,
          return_url:
            returnUrl ||
            `${window.location.origin}${window.location.pathname}?provider=stripe`,
        },
        redirect: "if_required",
      });

      if (result.error) {
        throw new Error(
          result.error.message || "No se pudo confirmar el pago con Stripe.",
        );
      }

      const confirmedPaymentIntentId =
        result.paymentIntent?.id || paymentIntentId;

      if (!confirmedPaymentIntentId) {
        throw new Error("Stripe no regresó el PaymentIntent confirmado.");
      }

      const confirmResponse = await postJson<StripeConfirmResponse>(
        confirmIntentUrl,
        {
          payment_id: localPaymentId,
          provider_payment_id: confirmedPaymentIntentId,
          stripe_status: result.paymentIntent?.status ?? null,
          idempotency_key: makeIdempotencyKey("stripe-confirm"),
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
      <PaymentElement
        onReady={() => {
          setReady(true);
          changeStatus("ready");
        }}
        onLoadError={(event) => {
          const errorMessage =
            event?.error?.message ||
            "No se pudo cargar el formulario de Stripe.";

          setReady(false);
          changeStatus("error", errorMessage);
        }}
      />

      {message && <div className="stag-herd-stripe-message">{message}</div>}

      <button
        type="submit"
        disabled={!stripe || !elements || !ready || processing}
        className="stag-herd-stripe-button"
      >
        {processing ? "Procesando..." : "Pagar con tarjeta"}
      </button>

      <p className="stag-herd-stripe-secure-text">
        Pago seguro procesado por Stripe.
      </p>
    </form>
  );
}
