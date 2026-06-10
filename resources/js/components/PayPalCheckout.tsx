import React, { useEffect, useId, useRef, useState } from "react";
import { loadScript } from "@paypal/paypal-js";

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
};

type CheckoutStatus = "idle" | "loading" | "success" | "error";

type MetadataValue = string | number | boolean | null;
type Metadata = Record<string, MetadataValue>;

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

    if (value === undefined || value === null || value === "") {
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
      `El backend no devolvió JSON. Status ${response.status}. Respuesta: ${text.substring(0, 300)}`,
    );
  }
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
}: Props) {
  const reactId = useId();
  const containerId = `stag-herd-paypal-buttons-${reactId.replace(/:/g, "")}`;

  const buttonsRef = useRef<any>(null);

  /*
   * Aquí guardamos temporalmente los datos que regresó /paypal/create.
   *
   * No es un Payment local todavía.
   * Solo sirve para que /paypal/capture pueda crear el Payment local
   * después de capturar exitosamente.
   */
  const checkoutContextRef = useRef<any>(null);

  const [status, setStatus] = useState<CheckoutStatus>("idle");
  const [message, setMessage] = useState<string>("");
  const [responsePayload, setResponsePayload] = useState<unknown>(null);

  useEffect(() => {
    let cancelled = false;

    async function initializePayPal() {
      setStatus("loading");
      setMessage("Inicializando PayPal...");
      setResponsePayload(null);

      if (!clientId) {
        setStatus("error");
        setMessage("Falta configurar PAYPAL_CLIENT_ID.");
        return;
      }

      if (!createOrderUrl) {
        setStatus("error");
        setMessage("Falta createOrderUrl.");
        return;
      }

      if (!captureOrderUrl) {
        setStatus("error");
        setMessage("Falta captureOrderUrl.");
        return;
      }

      const paypal = await loadScript({
        clientId,
        currency: currency || "MXN",
        intent: "capture",
        components: "buttons",
      });

      if (cancelled) {
        return;
      }

      if (!paypal?.Buttons) {
        setStatus("error");
        setMessage("No se pudo cargar PayPal Buttons.");
        return;
      }

      const buttons = paypal.Buttons({
        style: {
          layout: "vertical",
          color: "gold",
          shape: "rect",
          label: "paypal",
        },

        createOrder: async () => {
          setStatus("loading");
          setMessage("Creando orden de PayPal...");
          setResponsePayload(null);

          const currentAmount = Number(readInputValue("amount") || amount);
          const currentCurrency = readInputValue("currency") || currency;

          const currentExternalReference =
            readInputValue("external_reference") ||
            externalReference ||
            `PAYPAL-${Date.now()}`;

          const currentDescription =
            readInputValue("description") || description;

          const resolvedPayerEmail =
            readInputValue("payer_email") || payerEmail || "cliente@test.com";

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

          const response = await fetch(createOrderUrl, {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              Accept: "application/json",
              "X-CSRF-TOKEN": csrfToken,
              "X-Requested-With": "XMLHttpRequest",
            },
            body: JSON.stringify(payload),
          });

          const data = await parseJsonResponse(response);

          if (!response.ok || !data?.ok) {
            throw new Error(
              data?.message ||
                data?.error ||
                `No se pudo crear la orden de PayPal. Status ${response.status}`,
            );
          }

          const orderId =
            data?.provider_order_id || data?.paypal_order?.id || data?.id;

          if (!orderId) {
            throw new Error("El backend no regresó provider_order_id.");
          }

          /*
           * IMPORTANTE:
           * Aquí guardamos lo que después se mandará a /paypal/capture.
           */
          checkoutContextRef.current = data?.checkout_context ?? null;

          if (!checkoutContextRef.current) {
            throw new Error(
              "El backend creó la orden, pero no regresó checkout_context.",
            );
          }

          setResponsePayload(data);
          setMessage(
            `Orden creada en PayPal: ${orderId}. Aún no se guardó Payment local.`,
          );

          return orderId;
        },

        onApprove: async (data: any) => {
          setStatus("loading");
          setMessage("Capturando orden de PayPal...");

          if (!checkoutContextRef.current) {
            throw new Error(
              "No existe checkout_context. No se puede crear el Payment local después del capture.",
            );
          }

          const capturePayload = {
            provider_order_id: data?.orderID,
            ...checkoutContextRef.current,
          };

          /*
           * Debug útil:
           * revisa en la consola del navegador que aquí ya venga amount,
           * currency, metadata, etc.
           */
          console.log("PayPal capture payload:", capturePayload);

          const response = await fetch(captureOrderUrl, {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              Accept: "application/json",
              "X-CSRF-TOKEN": csrfToken,
              "X-Requested-With": "XMLHttpRequest",
            },
            body: JSON.stringify(capturePayload),
          });

          const responseData = await parseJsonResponse(response);

          if (!response.ok || !responseData?.ok) {
            throw new Error(
              responseData?.message ||
                responseData?.error ||
                `No se pudo capturar la orden de PayPal. Status ${response.status}`,
            );
          }

          setStatus("success");
          setMessage("Pago PayPal capturado y guardado localmente.");
          setResponsePayload(responseData);

          return responseData;
        },

        onCancel: (data: any) => {
          setStatus("idle");
          setMessage(
            "El comprador canceló el flujo de PayPal. No se guardó Payment local.",
          );
          setResponsePayload(data);
        },

        onError: (error: unknown) => {
          console.error(error);

          const errorMessage =
            error instanceof Error
              ? error.message
              : "PayPal devolvió un error inesperado.";

          setStatus("error");
          setMessage(errorMessage);
          setResponsePayload(error);
        },
      });

      if (!buttons.isEligible()) {
        setStatus("error");
        setMessage(
          "PayPal Buttons no está disponible para esta configuración.",
        );
        return;
      }

      await buttons.render(`#${containerId}`);

      buttonsRef.current = buttons;

      setStatus("idle");
      setMessage("Checkout PayPal listo.");
    }

    initializePayPal().catch((error) => {
      console.error(error);

      const errorMessage =
        error instanceof Error
          ? error.message
          : "No se pudo inicializar PayPal.";

      setStatus("error");
      setMessage(errorMessage);
      setResponsePayload(error);
    });

    return () => {
      cancelled = true;

      if (buttonsRef.current?.close) {
        buttonsRef.current.close();
      }

      buttonsRef.current = null;
    };
  }, [
    clientId,
    amount,
    currency,
    externalReference,
    payerEmail,
    description,
    createOrderUrl,
    captureOrderUrl,
    csrfToken,
    containerId,
  ]);

  return (
    <section>
      <div style={{ marginBottom: 16 }}>
        <strong>Referencia:</strong>{" "}
        {externalReference || "Se tomará del formulario"}
        <br />
        <strong>Monto:</strong> ${amount.toFixed(2)} {currency}
        <br />
        <strong>Email:</strong> {payerEmail || "cliente@test.com"}
      </div>

      <div id={containerId} />

      {message && (
        <div style={{ marginTop: 20 }}>
          <strong>Estado:</strong> {message}
        </div>
      )}

      {responsePayload !== null && (
        <pre
          style={{
            marginTop: 16,
            overflow: "auto",
            background: "#0f172a",
            color: "#e5e7eb",
            padding: 14,
            borderRadius: 12,
            fontSize: 12,
          }}
        >
          {JSON.stringify(responsePayload, null, 2)}
        </pre>
      )}

      {status === "success" && (
        <div style={{ marginTop: 16 }}>
          <a href={window.location.href}>Recargar pagos locales</a>
        </div>
      )}
    </section>
  );
}
