import {
  Elements,
  PaymentElement,
  useElements,
  useStripe,
} from "@stripe/react-stripe-js";

import {
  loadStripe,
  Stripe,
} from "@stripe/stripe-js";

import {
  FormEvent,
  useEffect,
  useMemo,
  useRef,
  useState,
} from "react";

export type StripeSavedCardResult = {
  customerId: string;
  paymentMethodId: string;
  setupIntentId: string;
  status: string;

  card: {
    brand?: string | null;
    lastFour?: string | null;
    expMonth?: number | null;
    expYear?: number | null;
    funding?: string | null;
    country?: string | null;
  };
};

export type StripeSaveCardStatus =
  | "idle"
  | "loading"
  | "ready"
  | "processing"
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

type SetupIntentResponse = {
  ok: boolean;
  message?: string;

  customer_id?: string;
  setup_intent_id?: string;
  client_secret?: string;
  status?: string;

  errors?: unknown;
};

type SetupCompleteResponse = {
  ok: boolean;
  message?: string;

  customer_id?: string;
  payment_method_id?: string;
  setup_intent_id?: string;
  status?: string;

  card?: {
    brand?: string | null;
    last_four?: string | null;
    exp_month?: number | null;
    exp_year?: number | null;
    funding?: string | null;
    country?: string | null;
  };

  errors?: unknown;
};

type Props = {
  publicKey: string;

  payerReference: string;
  payerEmail?: string;
  payerName?: string;

  /**
   * Se envía cuando el host ya tiene guardado el cus_...
   */
  customerId?: string;

  createSetupUrl: string;
  completeSetupUrl: string;

  csrfToken?: string;
  returnUrl?: string;

  metadata?: Metadata;

  buttonText?: string;

  onStatusChange?: (
    status: StripeSaveCardStatus,
    message?: string,
  ) => void;

  /**
   * El host debe guardar customerId y paymentMethodId.
   */
  onSuccess?: (
    result: StripeSavedCardResult,
    backendResponse:
      SetupCompleteResponse,
  ) => void | Promise<void>;

  onError?: (
    error: unknown,
  ) => void;
};

async function parseJsonResponse(
  response: Response,
): Promise<any> {
  const text =
    await response.text();

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
  const response = await fetch(
    url,
    {
      method: "POST",

      headers: {
        Accept: "application/json",
        "Content-Type":
          "application/json",

        ...(csrfToken
          ? {
              "X-CSRF-TOKEN":
                csrfToken,
            }
          : {}),
      },

      body: JSON.stringify(
        payload,
      ),
    },
  );

  const data =
    await parseJsonResponse(
      response,
    );

  if (
    !response.ok ||
    data?.ok === false
  ) {
    throw new Error(
      getBackendErrorMessage(
        data,
        "No se pudo guardar la tarjeta.",
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

function readMetadataFromPage(): Metadata {
  const metadata: Metadata = {};

  const elements =
    document.querySelectorAll<
      | HTMLInputElement
      | HTMLTextAreaElement
      | HTMLSelectElement
    >(
      "[data-stag-herd-metadata]",
    );

  elements.forEach(
    (element) => {
      const key =
        element.dataset
          .stagHerdMetadata;

      if (!key) {
        return;
      }

      const value =
        element.value?.trim();

      if (!value) {
        return;
      }

      metadata[key] = value;
    },
  );

  return metadata;
}

function normalizeResult(
  response:
    SetupCompleteResponse,
): StripeSavedCardResult {
  if (!response.customer_id) {
    throw new Error(
      "El backend no regresó customer_id.",
    );
  }

  if (
    !response.payment_method_id
  ) {
    throw new Error(
      "El backend no regresó payment_method_id.",
    );
  }

  if (
    !response.setup_intent_id
  ) {
    throw new Error(
      "El backend no regresó setup_intent_id.",
    );
  }

  return {
    customerId:
      response.customer_id,

    paymentMethodId:
      response.payment_method_id,

    setupIntentId:
      response.setup_intent_id,

    status:
      response.status ??
      "succeeded",

    card: {
      brand:
        response.card?.brand ??
        null,

      lastFour:
        response.card?.last_four ??
        null,

      expMonth:
        response.card?.exp_month ??
        null,

      expYear:
        response.card?.exp_year ??
        null,

      funding:
        response.card?.funding ??
        null,

      country:
        response.card?.country ??
        null,
    },
  };
}

export function StripeSaveCard({
  publicKey,
  payerReference,
  payerEmail,
  payerName,
  customerId,
  createSetupUrl,
  completeSetupUrl,
  csrfToken,
  returnUrl,
  metadata = {},
  buttonText =
    "Guardar tarjeta",
  onStatusChange,
  onSuccess,
  onError,
}: Props) {
  const [clientSecret, setClientSecret] =
    useState<string | null>(
      null,
    );

  const [
    resolvedCustomerId,
    setResolvedCustomerId,
  ] = useState<string | null>(
    customerId ?? null,
  );

  const [
    setupIntentId,
    setSetupIntentId,
  ] = useState<string | null>(
    null,
  );

  const [status, setStatus] =
    useState<StripeSaveCardStatus>(
      "idle",
    );

  const [message, setMessage] =
    useState("");

  const onStatusChangeRef =
    useRef(onStatusChange);

  const onSuccessRef =
    useRef(onSuccess);

  const onErrorRef =
    useRef(onError);

  useEffect(() => {
    onStatusChangeRef.current =
      onStatusChange;
  }, [onStatusChange]);

  useEffect(() => {
    onSuccessRef.current =
      onSuccess;
  }, [onSuccess]);

  useEffect(() => {
    onErrorRef.current =
      onError;
  }, [onError]);

  const stripePromise = useMemo<
    Promise<Stripe | null> | null
  >(() => {
    if (!publicKey) {
      return null;
    }

    return loadStripe(publicKey);
  }, [publicKey]);

  const setupKey = useMemo(
    () =>
      JSON.stringify({
        payerReference,
        payerEmail,
        payerName,
        customerId,
        createSetupUrl,
      }),
    [
      payerReference,
      payerEmail,
      payerName,
      customerId,
      createSetupUrl,
    ],
  );

  function changeStatus(
    nextStatus:
      StripeSaveCardStatus,
    nextMessage = "",
  ): void {
    setStatus(nextStatus);
    setMessage(nextMessage);

    onStatusChangeRef.current?.(
      nextStatus,
      nextMessage,
    );
  }

  useEffect(() => {
    let cancelled = false;

    async function createSetupIntent() {
      if (!publicKey) {
        changeStatus(
          "error",
          "Falta configurar la llave pública de Stripe.",
        );

        return;
      }

      if (!payerReference) {
        changeStatus(
          "error",
          "Falta configurar payerReference.",
        );

        return;
      }

      if (!createSetupUrl) {
        changeStatus(
          "error",
          "Falta configurar createSetupUrl.",
        );

        return;
      }

      try {
        changeStatus(
          "loading",
          "Preparando formulario seguro...",
        );

        const response =
          await postJson<SetupIntentResponse>(
            createSetupUrl,
            {
              customer_id:
                customerId || null,

              payer_reference:
                payerReference,

              payer_email:
                payerEmail || null,

              payer_name:
                payerName || null,

              return_url:
                returnUrl || null,

              idempotency_key:
                makeIdempotencyKey(
                  "stripe-card-setup",
                ),

              metadata: {
                ...readMetadataFromPage(),
                ...metadata,
              },
            },
            csrfToken,
          );

        if (
          !response.client_secret
        ) {
          throw new Error(
            "Stripe no regresó client_secret.",
          );
        }

        if (
          !response.customer_id
        ) {
          throw new Error(
            "Stripe no regresó customer_id.",
          );
        }

        if (
          !response.setup_intent_id
        ) {
          throw new Error(
            "Stripe no regresó setup_intent_id.",
          );
        }

        if (cancelled) {
          return;
        }

        setClientSecret(
          response.client_secret,
        );

        setResolvedCustomerId(
          response.customer_id,
        );

        setSetupIntentId(
          response.setup_intent_id,
        );

        changeStatus("ready");
      } catch (error) {
        if (cancelled) {
          return;
        }

        const errorMessage =
          getErrorMessage(
            error,
            "No se pudo preparar el formulario de Stripe.",
          );

        changeStatus(
          "error",
          errorMessage,
        );

        onErrorRef.current?.(
          error,
        );
      }
    }

    setClientSecret(null);
    setSetupIntentId(null);

    setResolvedCustomerId(
      customerId ?? null,
    );

    void createSetupIntent();

    return () => {
      cancelled = true;
    };
  }, [
    setupKey,
    publicKey,
    payerReference,
    payerEmail,
    payerName,
    customerId,
    createSetupUrl,
    csrfToken,
    returnUrl,
  ]);

  const elementsOptions =
    useMemo(() => {
      if (!clientSecret) {
        return undefined;
      }

      return {
        clientSecret,

        appearance: {
          theme:
            "stripe" as const,

          variables: {
            borderRadius: "8px",

            fontFamily:
              "system-ui, sans-serif",
          },
        },
      };
    }, [clientSecret]);

  if (
    status === "loading"
  ) {
    return (
      <p role="status">
        {message ||
          "Preparando formulario seguro..."}
      </p>
    );
  }

  if (
    status === "error" &&
    !clientSecret
  ) {
    return (
      <p role="alert">
        {message ||
          "No se pudo cargar Stripe."}
      </p>
    );
  }

  if (
    !stripePromise ||
    !clientSecret ||
    !resolvedCustomerId ||
    !setupIntentId ||
    !elementsOptions
  ) {
    return null;
  }

  return (
    <Elements
      key={clientSecret}
      stripe={stripePromise}
      options={elementsOptions}
    >
      <StripeSaveCardForm
        customerId={
          resolvedCustomerId
        }
        setupIntentId={
          setupIntentId
        }
        completeSetupUrl={
          completeSetupUrl
        }
        csrfToken={csrfToken}
        returnUrl={returnUrl}
        buttonText={buttonText}
        onStatusChange={
          changeStatus
        }
        onSuccess={async (
          result,
          response,
        ) => {
          await onSuccessRef.current?.(
            result,
            response,
          );
        }}
        onError={(error) => {
          onErrorRef.current?.(
            error,
          );
        }}
      />
    </Elements>
  );
}

function StripeSaveCardForm({
  customerId,
  setupIntentId,
  completeSetupUrl,
  csrfToken,
  returnUrl,
  buttonText,
  onStatusChange,
  onSuccess,
  onError,
}: {
  customerId: string;
  setupIntentId: string;

  completeSetupUrl: string;

  csrfToken?: string;
  returnUrl?: string;

  buttonText: string;

  onStatusChange: (
    status:
      StripeSaveCardStatus,
    message?: string,
  ) => void;

  onSuccess: (
    result:
      StripeSavedCardResult,
    response:
      SetupCompleteResponse,
  ) => void | Promise<void>;

  onError?: (
    error: unknown,
  ) => void;
}) {
  const stripe = useStripe();
  const elements = useElements();

  const [ready, setReady] =
    useState(false);

  const [
    processing,
    setProcessing,
  ] = useState(false);

  const [message, setMessage] =
    useState("");

  const lockRef = useRef(false);

  function changeStatus(
    status:
      StripeSaveCardStatus,
    nextMessage = "",
  ): void {
    setMessage(nextMessage);

    onStatusChange(
      status,
      nextMessage,
    );
  }

  async function handleSubmit(
    event:
      FormEvent<HTMLFormElement>,
  ): Promise<void> {
    event.preventDefault();

    if (
      lockRef.current ||
      processing
    ) {
      return;
    }

    if (
      !stripe ||
      !elements
    ) {
      changeStatus(
        "error",
        "Stripe todavía no está listo.",
      );

      return;
    }

    if (!ready) {
      changeStatus(
        "error",
        "Espera a que cargue el formulario.",
      );

      return;
    }

    if (!completeSetupUrl) {
      changeStatus(
        "error",
        "Falta configurar completeSetupUrl.",
      );

      return;
    }

    lockRef.current = true;
    setProcessing(true);

    changeStatus(
      "processing",
      "Guardando tarjeta...",
    );

    try {
      const result =
        await stripe.confirmSetup({
          elements,

          confirmParams: {
            return_url:
              returnUrl ||
              `${window.location.origin}${window.location.pathname}?provider=stripe&action=save-card`,
          },

          redirect:
            "if_required",
        });

      if (result.error) {
        throw new Error(
          result.error.message ||
            "No se pudo guardar la tarjeta.",
        );
      }

      const confirmedSetupIntentId =
        result.setupIntent?.id ||
        setupIntentId;

      if (
        !confirmedSetupIntentId
      ) {
        throw new Error(
          "Stripe no regresó el SetupIntent confirmado.",
        );
      }

      const response =
        await postJson<SetupCompleteResponse>(
          completeSetupUrl,
          {
            setup_intent_id:
              confirmedSetupIntentId,

            customer_id:
              customerId,
          },
          csrfToken,
        );

      const normalizedResult =
        normalizeResult(response);

      changeStatus(
        "success",
        "Tarjeta guardada correctamente.",
      );

      await onSuccess(
        normalizedResult,
        response,
      );
    } catch (error) {
      const errorMessage =
        getErrorMessage(
          error,
          "No se pudo guardar la tarjeta.",
        );

      changeStatus(
        "error",
        errorMessage,
      );

      onError?.(error);
    } finally {
      lockRef.current = false;
      setProcessing(false);
    }
  }

  return (
    <form onSubmit={handleSubmit}>
      <PaymentElement
        options={{
          layout: "tabs",
        }}
        onReady={() => {
          setReady(true);

          changeStatus(
            "ready",
          );
        }}
        onLoadError={(event) => {
          const errorMessage =
            event.error?.message ||
            "No se pudo cargar el formulario de Stripe.";

          setReady(false);

          changeStatus(
            "error",
            errorMessage,
          );
        }}
      />

      {message && (
        <p role="status">
          {message}
        </p>
      )}

      <button
        type="submit"
        disabled={
          !stripe ||
          !elements ||
          !ready ||
          processing
        }
      >
        {processing
          ? "Guardando..."
          : buttonText}
      </button>

      <small>
        Los datos completos de la
        tarjeta son procesados por
        Stripe y no pasan por tu
        aplicación.
      </small>
    </form>
  );
}