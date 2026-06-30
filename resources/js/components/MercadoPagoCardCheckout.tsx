import { loadMercadoPago } from "@mercadopago/sdk-js";
import { CSSProperties, useEffect, useId, useRef } from "react";

type Props = {
  publicKey: string;
  amount: number;
  currency: string;
  externalReference: string;
  payerEmail?: string;
  showEmailInput?: boolean;
  processUrl: string;
  csrfToken: string;
  description?: string;

  className?: string;
  containerStyle?: CSSProperties;

  locale?: "es-MX" | "es-AR" | "es-CL" | "es-CO" | "es-PE" | "pt-BR" | "en-US";

  theme?: "default" | "dark" | "bootstrap" | "flat";
  minInstallments?: number;
  maxInstallments?: number;

  onStatusChange?: (
    status: "idle" | "loading" | "ready" | "success" | "error",
    message?: string,
  ) => void;

  onSuccess?: (data: any) => void | Promise<void>;
  onError?: (error: unknown) => void;
};

type MetadataValue = string | number | boolean | null;
type Metadata = Record<string, MetadataValue>;

declare global {
  interface Window {
    MercadoPago?: any;
    MP_DEVICE_SESSION_ID?: string;
  }
}

function readInputValue(id: string): string {
  const element = document.getElementById(id) as
    | HTMLInputElement
    | HTMLTextAreaElement
    | HTMLSelectElement
    | null;

  return element?.value?.trim() ?? "";
}

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

function loadMercadoPagoSecurity(): Promise<void> {
  return new Promise((resolve) => {
    if (window.MP_DEVICE_SESSION_ID) {
      resolve();
      return;
    }

    const existingScript = document.querySelector(
      'script[src="https://www.mercadopago.com/v2/security.js"]',
    );

    if (existingScript) {
      existingScript.addEventListener("load", () => resolve(), {
        once: true,
      });

      existingScript.addEventListener("error", () => resolve(), {
        once: true,
      });

      return;
    }

    const script = document.createElement("script");

    script.src = "https://www.mercadopago.com/v2/security.js";
    script.async = true;
    script.setAttribute("view", "checkout");

    script.onload = () => resolve();

    script.onerror = () => {
      console.warn("No se pudo cargar security.js de Mercado Pago.");
      resolve();
    };

    document.body.appendChild(script);
  });
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

function getErrorMessage(error: unknown, fallback: string): string {
  if (error instanceof Error && error.message.trim() !== "") {
    return error.message;
  }

  if (error && typeof error === "object") {
    const e = error as {
      message?: unknown;
      cause?: unknown;
      type?: unknown;
    };

    if (typeof e.message === "string" && e.message.trim() !== "") {
      return e.message;
    }

    if (typeof e.cause === "string" && e.cause.trim() !== "") {
      return e.cause;
    }

    try {
      return JSON.stringify(error);
    } catch {
      return fallback;
    }
  }

  return fallback;
}

function makeIdempotencyKey(): string {
  if (typeof crypto !== "undefined" && "randomUUID" in crypto) {
    return crypto.randomUUID().slice(0, 64);
  }

  return `mp-${Date.now()}-${Math.random().toString(16).slice(2)}`.slice(0, 64);
}

export function MercadoPagoCardCheckout({
  publicKey,
  amount,
  currency,
  externalReference,
  payerEmail,
  showEmailInput = false,
  processUrl,
  csrfToken,
  description = "Pago desde Mercado Pago Card Brick",

  className,
  containerStyle,

  locale = "es-MX",
  theme = "default",
  minInstallments = 1,
  maxInstallments = 1,

  onStatusChange,
  onSuccess,
  onError,
}: Props) {
  const reactId = useId();

  const containerId = `stag-herd-card-payment-brick-${reactId.replace(
    /:/g,
    "",
  )}`;

  const controllerRef = useRef<any>(null);
  const initializingRef = useRef(false);

  const latestPropsRef = useRef({
    amount,
    currency,
    externalReference,
    payerEmail,
    showEmailInput,
    processUrl,
    csrfToken,
    description,
  });

  const onStatusChangeRef = useRef<Props["onStatusChange"]>(onStatusChange);
  const onSuccessRef = useRef<Props["onSuccess"]>(onSuccess);
  const onErrorRef = useRef<Props["onError"]>(onError);

  useEffect(() => {
    latestPropsRef.current = {
      amount,
      currency,
      externalReference,
      payerEmail,
      showEmailInput,
      processUrl,
      csrfToken,
      description,
    };
  }, [
    amount,
    currency,
    externalReference,
    payerEmail,
    showEmailInput,
    processUrl,
    csrfToken,
    description,
  ]);

  useEffect(() => {
    onStatusChangeRef.current = onStatusChange;
  }, [onStatusChange]);

  useEffect(() => {
    onSuccessRef.current = onSuccess;
  }, [onSuccess]);

  useEffect(() => {
    onErrorRef.current = onError;
  }, [onError]);

  function notifyStatus(
    status: "idle" | "loading" | "ready" | "success" | "error",
    message?: string,
  ): void {
    if (onStatusChangeRef.current) {
      onStatusChangeRef.current(status, message);
    }
  }

  function notifyError(error: unknown, fallback: string): void {
    console.error(error);

    notifyStatus("error", getErrorMessage(error, fallback));

    if (onErrorRef.current) {
      onErrorRef.current(error);
    }
  }

  useEffect(() => {
    let cancelled = false;

    async function initializeBrick() {
      if (initializingRef.current || controllerRef.current) {
        console.warn(
          "[MP DEBUG] Brick ya inicializado o inicializando. Se omite.",
          {
            containerId,
          },
        );

        return;
      }

      initializingRef.current = true;
      notifyStatus("loading", "Inicializando Mercado Pago...");

      try {
        if (!publicKey) {
          throw new Error("Falta configurar MERCADO_PAGO_PUBLIC_KEY.");
        }

        if (!processUrl) {
          throw new Error("Falta configurar processUrl.");
        }

        if (!amount || amount <= 0) {
          throw new Error("El monto debe ser mayor a cero.");
        }

        if (!showEmailInput && !payerEmail) {
          throw new Error(
            "Configuración inválida: si ocultas el input de email, debes enviar payerEmail prellenado.",
          );
        }

        const container = document.getElementById(containerId);

        if (!container) {
          return;
        }

        container.innerHTML = "";

        await loadMercadoPago();
        await loadMercadoPagoSecurity();
        console.groupEnd();

        if (cancelled) {
          return;
        }

        if (!window.MercadoPago) {
          throw new Error("No se pudo cargar Mercado Pago.");
        }

        const mp = new window.MercadoPago(publicKey, {
          locale,
        });

        const bricksBuilder = mp.bricks();

        const controller = await bricksBuilder.create(
          "cardPayment",
          containerId,
          {
            initialization: {
              amount: Number(amount),
              ...(!showEmailInput && payerEmail
                ? {
                    payer: {
                      email: payerEmail,
                    },
                  }
                : {}),
            },

            customization: {
              visual: {
                style: {
                  theme,
                },
              },
              paymentMethods: {
                minInstallments,
                maxInstallments,
              },
            },

            callbacks: {
              onReady: () => {
                notifyStatus("ready", "Checkout Mercado Pago listo.");
              },

              onSubmit: async (rawCardFormData: any, additionalData?: any) => {
                const cardFormData =
                  rawCardFormData?.formData ?? rawCardFormData;
                const token = String(
                  cardFormData?.token ?? cardFormData?.card_token_id ?? "",
                ).trim();

                if (!token) {
                  throw new Error("Mercado Pago no generó token de tarjeta.");
                }

                const paymentMethodId = String(
                  cardFormData?.payment_method_id ?? "",
                ).trim();

                if (!paymentMethodId) {
                  throw new Error("Mercado Pago no generó payment_method_id.");
                }

                notifyStatus("loading", "Procesando pago...");

                const current = latestPropsRef.current;

                const currentAmount = Number(
                  readInputValue("amount") || current.amount,
                );

                const currentCurrency =
                  readInputValue("currency") || current.currency || "MXN";

                const currentExternalReference =
                  readInputValue("external_reference") ||
                  current.externalReference ||
                  `BRICK-${Date.now()}`;

                const currentDescription =
                  readInputValue("description") || current.description;

                const resolvedPayerEmail =
                  cardFormData?.payer?.email ||
                  readInputValue("payer_email") ||
                  current.payerEmail ||
                  "";

                if (!resolvedPayerEmail) {
                  throw new Error(
                    "Falta el correo del pagador para Mercado Pago.",
                  );
                }

                const metadata = readMetadataFromPage();

                const idempotencyKey = makeIdempotencyKey();
                const deviceId = window.MP_DEVICE_SESSION_ID ?? null;

                const payload = {
                  provider: "mercado_pago",
                  method: "card",

                  amount: currentAmount,
                  currency: currentCurrency,

                  external_reference: currentExternalReference,
                  payer_email: resolvedPayerEmail,
                  description: currentDescription,

                  idempotency_key: idempotencyKey,
                  device_id: deviceId,

                  metadata,

                  mercado_pago: {
                    token,
                    payment_method_id: paymentMethodId,
                    issuer_id: cardFormData?.issuer_id ?? null,
                    installments: Number(cardFormData?.installments ?? 1),
                    payer: {
                      ...(cardFormData?.payer ?? {}),
                      email: resolvedPayerEmail,
                    },
                    payment_type_id:
                      additionalData?.paymentTypeId ??
                      rawCardFormData?.selectedPaymentMethod ??
                      null,
                    idempotency_key: idempotencyKey,
                    device_id: deviceId,
                  },

                  raw_form_data: cardFormData,
                };

                try {
                  const response = await fetch(current.processUrl, {
                    method: "POST",
                    headers: {
                      "Content-Type": "application/json",
                      Accept: "application/json",
                      "X-CSRF-TOKEN": current.csrfToken,
                      "X-Requested-With": "XMLHttpRequest",
                      "X-Idempotency-Key": idempotencyKey,
                    },
                    credentials: "include",
                    body: JSON.stringify(payload),
                  });

                  const data = await parseJsonResponse(response);

                  if (!response.ok) {
                    throw new Error(
                      data?.message ||
                        data?.error ||
                        `No se pudo procesar el pago. Status ${response.status}`,
                    );
                  }

                  notifyStatus("success", "Pago enviado correctamente.");

                  if (onSuccessRef.current) {
                    await onSuccessRef.current(data);
                  }

                  return data;
                } catch (error) {
                  notifyError(error, "Error inesperado al procesar el pago.");

                  throw error;
                }
              },

              onError: (error: unknown) => {
                console.group("[MP DEBUG] Brick onError");
                console.log("raw error:", error);

                try {
                  console.log("JSON:", JSON.stringify(error, null, 2));
                } catch {
                  console.log("JSON:", "No se pudo serializar el error.");
                }

                console.log("type:", (error as any)?.type);
                console.log("cause:", (error as any)?.cause);
                console.log("message:", (error as any)?.message);
                console.trace("trace");
                console.groupEnd();

                notifyError(
                  error,
                  "Mercado Pago devolvió un error al inicializar o tokenizar el formulario.",
                );
              },
            },
          },
        );

        if (cancelled) {
          if (controller?.unmount) {
            controller.unmount();
          }

          return;
        }

        controllerRef.current = controller;
        initializingRef.current = false;
      } catch (error) {
        initializingRef.current = false;

        throw error;
      }
    }

    initializeBrick().catch((error) => {
      if (cancelled) {
        return;
      }

      notifyError(error, "No se pudo inicializar el checkout.");
    });

    return () => {
      cancelled = true;
      initializingRef.current = false;

      try {
        if (controllerRef.current?.unmount) {
          controllerRef.current.unmount();
        }
      } catch (error) {
        console.warn("No se pudo desmontar Mercado Pago Card Brick.", error);
      }

      controllerRef.current = null;
    };
  }, [
    publicKey,
    amount,
    payerEmail,
    showEmailInput,
    processUrl,
    containerId,
    locale,
    theme,
    minInstallments,
    maxInstallments,
    csrfToken,
  ]);

  return (
    <div
      id={containerId}
      className={className}
      style={{
        width: "100%",
        ...containerStyle,
      }}
    />
  );
}
