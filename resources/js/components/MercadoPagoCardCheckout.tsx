import React, { useEffect, useId, useRef, useState } from "react";
import { loadMercadoPago } from "@mercadopago/sdk-js";

type Props = {
  publicKey: string;
  amount: number;
  currency: string;
  externalReference: string;
  payerEmail: string;
  processUrl: string;
  csrfToken: string;
  description?: string;
};

type CheckoutStatus = "idle" | "loading" | "success" | "error";

declare global {
  interface Window {
    MercadoPago?: any;
  }
}

export function MercadoPagoCardCheckout({
  publicKey,
  amount,
  currency,
  externalReference,
  payerEmail,
  processUrl,
  csrfToken,
  description = "Pago desde Mercado Pago Card Brick",
}: Props) {
  const reactId = useId();
  const containerId = `stag-herd-card-payment-brick-${reactId.replace(/:/g, "")}`;

  const controllerRef = useRef<any>(null);

  const [status, setStatus] = useState<CheckoutStatus>("idle");
  const [message, setMessage] = useState<string>("");
  const [responsePayload, setResponsePayload] = useState<unknown>(null);

  useEffect(() => {
    let cancelled = false;

    async function initializeBrick() {
      setStatus("loading");
      setMessage("Inicializando Mercado Pago...");
      setResponsePayload(null);

      if (!publicKey) {
        setStatus("error");
        setMessage("Falta configurar MERCADO_PAGO_PUBLIC_KEY.");
        return;
      }

      if (!processUrl) {
        setStatus("error");
        setMessage("Falta configurar processUrl.");
        return;
      }

      if (!amount || amount <= 0) {
        setStatus("error");
        setMessage("El monto debe ser mayor a cero.");
        return;
      }

      await loadMercadoPago();

      if (cancelled) {
        return;
      }

      if (!window.MercadoPago) {
        setStatus("error");
        setMessage("No se pudo cargar Mercado Pago.");
        return;
      }

      const mp = new window.MercadoPago(publicKey, {
        locale: "es-MX",
      });

      const bricksBuilder = mp.bricks();

      const controller = await bricksBuilder.create(
        "cardPayment",
        containerId,
        {
          initialization: {
            amount,
            payer: {
              email: payerEmail || "cliente@test.com",
            },
          },

          customization: {
            visual: {
              style: {
                theme: "default",
              },
            },
            paymentMethods: {
              maxInstallments: 1,
            },
          },

          callbacks: {
            onReady: () => {
              setStatus("idle");
              setMessage("Checkout listo.");
            },

            onSubmit: async (cardFormData: any) => {
              setStatus("loading");
              setMessage("Procesando pago...");
              setResponsePayload(null);

              const resolvedPayerEmail =
                cardFormData?.payer?.email || payerEmail || "cliente@test.com";

              const payload = {
                provider: "mercado_pago",
                method: "card",

                amount,
                currency,
                external_reference: externalReference,
                payer_email: resolvedPayerEmail,
                description,

                mercado_pago: {
                  token: cardFormData?.token,
                  payment_method_id: cardFormData?.payment_method_id,
                  issuer_id: cardFormData?.issuer_id,
                  installments: Number(cardFormData?.installments ?? 1),
                  payer: {
                    ...(cardFormData?.payer ?? {}),
                    email: resolvedPayerEmail,
                  },
                },

                raw_form_data: cardFormData,
              };

              try {
                const response = await fetch(processUrl, {
                  method: "POST",
                  headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                    "X-CSRF-TOKEN": csrfToken,
                    "X-Requested-With": "XMLHttpRequest",
                  },
                  body: JSON.stringify(payload),
                });

                const responseText = await response.text();

                let data: any = null;

                try {
                  data = responseText ? JSON.parse(responseText) : null;
                } catch {
                  throw new Error(
                    `El backend no devolvió JSON. Status ${response.status}. Respuesta: ${responseText.substring(0, 300)}`,
                  );
                }

                if (!response.ok) {
                  throw new Error(
                    data?.message ||
                      data?.error ||
                      `No se pudo procesar el pago. Status ${response.status}`,
                  );
                }

                setStatus("success");
                setMessage("Pago enviado correctamente.");
                setResponsePayload(data);

                return data;
              } catch (error) {
                const errorMessage =
                  error instanceof Error
                    ? error.message
                    : "Error inesperado al procesar el pago.";

                setStatus("error");
                setMessage(errorMessage);
                setResponsePayload(error);

                throw error;
              }
            },

            onError: (error: unknown) => {
              console.error(error);

              setStatus("error");
              setMessage(
                "Mercado Pago devolvió un error al inicializar el formulario.",
              );
              setResponsePayload(error);
            },
          },
        },
      );

      controllerRef.current = controller;
    }

    initializeBrick().catch((error) => {
      console.error(error);

      setStatus("error");
      setMessage("No se pudo inicializar el checkout.");
      setResponsePayload(error);
    });

    return () => {
      cancelled = true;

      if (controllerRef.current?.unmount) {
        controllerRef.current.unmount();
      }

      controllerRef.current = null;
    };
  }, [
    publicKey,
    amount,
    currency,
    externalReference,
    payerEmail,
    processUrl,
    csrfToken,
    description,
    containerId,
  ]);

  return (
    <section>
      <div style={{ marginBottom: 16 }}>
        <strong>Referencia:</strong> {externalReference || "Sin referencia"}
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
    </section>
  );
}
