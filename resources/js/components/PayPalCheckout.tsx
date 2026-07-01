import { loadScript } from "@paypal/paypal-js";
import { CSSProperties, useEffect, useId, useRef, useState } from "react";

type PayPalButtonStyle = {
  layout?: "vertical" | "horizontal";
  color?: "gold" | "blue" | "silver" | "white" | "black";
  shape?: "rect" | "pill";
  label?: "paypal" | "checkout" | "buynow" | "pay" | "installment";
  height?: number;
  tagline?: boolean;
};

type CheckoutStatus = "idle" | "loading" | "ready" | "success" | "error";

type MetadataValue = string | number | boolean | null;
type Metadata = Record<string, MetadataValue>;

type Props = {
  clientId: string;
  amount: number;
  currency: string;
  externalReference: string;
  payerEmail: string;
  description?: string;
  createOrderUrl: string;
  captureOrderUrl: string;
  csrfToken: string;

  className?: string;
  containerStyle?: CSSProperties;
  buttonStyle?: PayPalButtonStyle;

  onStatusChange?: (status: CheckoutStatus, message?: string) => void;
  onSuccess?: (payload: any) => void | Promise<void>;
  onError?: (error: unknown) => void;
  onCancel?: (payload: unknown) => void;
};

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

  return fallback;
}

function getBackendErrorMessage(data: any, fallback: string): string {
  return (
    data?.message ||
    data?.errors?.[0] ||
    data?.error ||
    data?.errors?.paypal_description ||
    data?.errors?.response?.response_json?.details?.[0]?.description ||
    data?.response_json?.details?.[0]?.description ||
    fallback
  );
}

function isZoidDestroyedError(error: unknown): boolean {
  const message = getErrorMessage(error, "").toLowerCase();

  return (
    message.includes("zoid destroyed all components") ||
    message.includes("all components destroyed") ||
    message.includes("component destroyed")
  );
}

function normalizeButtonStyle(style?: PayPalButtonStyle): PayPalButtonStyle {
  const height = style?.height;

  return {
    layout: style?.layout ?? "vertical",
    color: style?.color ?? "gold",
    shape: style?.shape ?? "pill",
    label: style?.label ?? "paypal",
    tagline: style?.tagline ?? false,
    ...(typeof height === "number"
      ? {
          height: Math.min(55, Math.max(25, height)),
        }
      : {
          height: 45,
        }),
  };
}

export function PayPalCheckout({
  clientId,
  amount,
  currency,
  externalReference,
  payerEmail,
  description = "Pago desde PayPal Checkout",
  createOrderUrl,
  captureOrderUrl,
  csrfToken,
  className,
  containerStyle,
  buttonStyle,
  onStatusChange,
  onSuccess,
  onError,
  onCancel,
}: Props) {
  const reactId = useId();
  const containerId = `stag-herd-paypal-buttons-${reactId.replace(/:/g, "")}`;

  const buttonsRef = useRef<any>(null);
  const checkoutContextRef = useRef<any>(null);

  const [checkoutStatus, setCheckoutStatus] = useState<CheckoutStatus>("idle");
  const [checkoutMessage, setCheckoutMessage] = useState<string>("");

  const latestPropsRef = useRef({
    amount,
    currency,
    externalReference,
    payerEmail,
    description,
    createOrderUrl,
    captureOrderUrl,
    csrfToken,
  });

  const onStatusChangeRef = useRef<Props["onStatusChange"]>(onStatusChange);
  const onSuccessRef = useRef<Props["onSuccess"]>(onSuccess);
  const onErrorRef = useRef<Props["onError"]>(onError);
  const onCancelRef = useRef<Props["onCancel"]>(onCancel);

  useEffect(() => {
    latestPropsRef.current = {
      amount,
      currency,
      externalReference,
      payerEmail,
      description,
      createOrderUrl,
      captureOrderUrl,
      csrfToken,
    };
  }, [
    amount,
    currency,
    externalReference,
    payerEmail,
    description,
    createOrderUrl,
    captureOrderUrl,
    csrfToken,
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

  useEffect(() => {
    onCancelRef.current = onCancel;
  }, [onCancel]);

  function notifyStatus(status: CheckoutStatus, message?: string): void {
    setCheckoutStatus(status);
    setCheckoutMessage(message ?? "");

    if (onStatusChangeRef.current) {
      onStatusChangeRef.current(status, message);
    }
  }

  function notifySilentStatus(status: CheckoutStatus): void {
    setCheckoutStatus(status);
    setCheckoutMessage("");

    if (onStatusChangeRef.current) {
      onStatusChangeRef.current(status);
    }
  }

  function notifyError(error: unknown, fallback: string): void {
    if (isZoidDestroyedError(error)) {
      return;
    }

    console.error(error);

    const message = getErrorMessage(error, fallback);

    notifyStatus("error", message);

    if (onErrorRef.current) {
      onErrorRef.current(error);
    }
  }

  useEffect(() => {
    let cancelled = false;

    async function initializePayPal() {
      notifySilentStatus("loading");

      if (!clientId) {
        throw new Error("Falta configurar PAYPAL_CLIENT_ID.");
      }

      if (!createOrderUrl) {
        throw new Error("Falta createOrderUrl.");
      }

      if (!captureOrderUrl) {
        throw new Error("Falta captureOrderUrl.");
      }

      const container = document.getElementById(containerId);

      if (!container) {
        return;
      }

      container.innerHTML = "";

      const paypal = await loadScript({
        clientId,
        currency: currency || "MXN",
        intent: "capture",
        components: "buttons",
        enableFunding: "card",
      });

      if (cancelled) {
        return;
      }

      if (!paypal?.Buttons) {
        throw new Error("No se pudo cargar PayPal Buttons.");
      }

      const buttons = paypal.Buttons({
        style: normalizeButtonStyle(buttonStyle),

        createOrder: async () => {
          try {
            notifySilentStatus("loading");

            const current = latestPropsRef.current;

            const currentAmount = Number(
              readInputValue("amount") || current.amount,
            );

            const currentCurrency =
              readInputValue("currency") || current.currency || "MXN";

            const currentExternalReference =
              readInputValue("external_reference") ||
              current.externalReference ||
              `PAYPAL-${Date.now()}`;

            const currentDescription =
              readInputValue("description") || current.description;

            const resolvedPayerEmail =
              readInputValue("payer_email") ||
              current.payerEmail ||
              "cliente@test.com";

            const metadata = readMetadataFromPage();

            const payload = {
              amount: currentAmount,
              currency: currentCurrency,

              external_reference: currentExternalReference,
              payer_email: resolvedPayerEmail,
              description: currentDescription,

              metadata,

              paypal: {
                intent: "CAPTURE",
                brand_name:
                  readInputValue("paypal_brand_name") || "Stag Herd Demo",
                landing_page: readInputValue("paypal_landing_page") || "LOGIN",
                user_action: readInputValue("paypal_user_action") || "PAY_NOW",
                shipping_preference:
                  readInputValue("paypal_shipping_preference") || "NO_SHIPPING",
                invoice_id: readInputValue("paypal_invoice_id") || undefined,
              },
            };

            const response = await fetch(current.createOrderUrl, {
              method: "POST",
              headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-CSRF-TOKEN": current.csrfToken,
                "X-Requested-With": "XMLHttpRequest",
              },
              credentials: "include",
              body: JSON.stringify(payload),
            });

            const data = await parseJsonResponse(response);

            if (!response.ok || !data?.ok) {
              throw new Error(
                getBackendErrorMessage(
                  data,
                  `No se pudo crear la orden de PayPal. Status ${response.status}`,
                ),
              );
            }

            const orderId =
              data?.provider_order_id || data?.paypal_order?.id || data?.id;

            if (!orderId) {
              throw new Error("El backend no regresó provider_order_id.");
            }

            checkoutContextRef.current = data?.checkout_context ?? null;

            if (!checkoutContextRef.current) {
              throw new Error(
                "El backend creó la orden, pero no regresó checkout_context.",
              );
            }

            notifySilentStatus("loading");

            return String(orderId);
          } catch (error) {
            notifyError(error, "No ha sido posible crear la orden de PayPal.");

            throw error;
          }
        },

        onApprove: async (data: any): Promise<void> => {
          try {
            notifySilentStatus("loading");

            const current = latestPropsRef.current;

            if (!checkoutContextRef.current) {
              throw new Error(
                "No existe checkout_context. No se puede crear el Payment después del capture.",
              );
            }

            const capturePayload = {
              provider_order_id: data?.orderID,
              ...checkoutContextRef.current,
            };

            const response = await fetch(current.captureOrderUrl, {
              method: "POST",
              headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-CSRF-TOKEN": current.csrfToken,
                "X-Requested-With": "XMLHttpRequest",
              },
              credentials: "include",
              body: JSON.stringify(capturePayload),
            });

            const responseData = await parseJsonResponse(response);

            if (!response.ok || !responseData?.ok) {
              throw new Error(
                getBackendErrorMessage(
                  responseData,
                  `No se pudo capturar la orden de PayPal. Status ${response.status}`,
                ),
              );
            }

            notifySilentStatus("success");

            if (onSuccessRef.current) {
              await onSuccessRef.current(responseData);
            }
          } catch (error) {
            notifyError(error, "No ha sido posible procesar el pago.");

            /**
             * Importante:
             * No relanzar el error aquí.
             *
             * Si haces throw error dentro de onApprove,
             * PayPal lo registra como:
             * onApprove_non_resume_flow_merchant_callback_rejected
             */
          }
        },

        onCancel: (data: any) => {
          notifySilentStatus("idle");

          if (onCancelRef.current) {
            onCancelRef.current(data);
          }
        },

        onError: (error: unknown) => {
          notifyError(error, "PayPal devolvió un error inesperado.");
        },
      });

      if (!buttons.isEligible()) {
        throw new Error(
          "PayPal Buttons no está disponible para esta configuración.",
        );
      }

      await buttons.render(`#${containerId}`);

      if (cancelled) {
        return;
      }

      buttonsRef.current = buttons;

      notifySilentStatus("ready");
    }

    initializePayPal().catch((error) => {
      if (cancelled || isZoidDestroyedError(error)) {
        return;
      }

      notifyError(error, "No se pudo inicializar PayPal.");
    });

    return () => {
      cancelled = true;
      buttonsRef.current = null;
      checkoutContextRef.current = null;
    };
  }, [
    clientId,
    currency,
    createOrderUrl,
    captureOrderUrl,
    csrfToken,
    containerId,
    buttonStyle,
  ]);

  return (
    <div
      className={className}
      style={{
        width: "100%",
        maxWidth: 420,
        ...containerStyle,
      }}
    >
      {checkoutStatus === "error" && checkoutMessage && (
        <div
          role="alert"
          style={{
            marginBottom: 12,
            padding: "10px 12px",
            borderRadius: 8,
            border: "1px solid #dc2626",
            background: "#fef2f2",
            color: "#991b1b",
            fontSize: 14,
            lineHeight: 1.4,
          }}
        >
          {checkoutMessage}
        </div>
      )}

      <div id={containerId} />
    </div>
  );
}
