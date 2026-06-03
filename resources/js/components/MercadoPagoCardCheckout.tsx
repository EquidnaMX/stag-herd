import React, { useEffect, useRef, useState } from "react";
import { loadMercadoPago } from "@mercadopago/sdk-js";

type Props = {
  publicKey: string;
  amount: number;
  currency: string;
  externalReference: string;
  payerEmail: string;
  processUrl: string;
  csrfToken: string;
};

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
}: Props) {
  const brickInitialized = useRef(false);
  const [status, setStatus] = useState<
    "idle" | "loading" | "success" | "error"
  >("idle");
  const [message, setMessage] = useState<string>("");

  useEffect(() => {
    async function initializeBrick() {
      if (brickInitialized.current) {
        return;
      }

      brickInitialized.current = true;

      await loadMercadoPago();

      if (!window.MercadoPago) {
        setStatus("error");
        setMessage("No se pudo cargar Mercado Pago.");
        return;
      }

      const mp = new window.MercadoPago(publicKey, {
        locale: "es-MX",
      });

      const bricksBuilder = mp.bricks();

      await bricksBuilder.create("cardPayment", "cardPaymentBrick_container", {
        initialization: {
          amount,
          payer: {
            email: payerEmail,
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
            setMessage("");
          },

          onSubmit: async (cardFormData: any) => {
            setStatus("loading");
            setMessage("Procesando pago...");

            try {
              const response = await fetch(processUrl, {
                method: "POST",
                headers: {
                  "Content-Type": "application/json",
                  Accept: "application/json",
                  "X-CSRF-TOKEN": csrfToken,
                },
                body: JSON.stringify({
                  token: cardFormData.token,
                  payment_method_id: cardFormData.payment_method_id,
                  issuer_id: cardFormData.issuer_id,
                  installments: Number(cardFormData.installments ?? 1),
                  payer: {
                    email: cardFormData.payer?.email ?? payerEmail,
                  },
                  amount,
                  currency,
                  external_reference: externalReference,
                }),
              });

              const data = await response.json();

              if (!response.ok) {
                throw new Error(data.message ?? "No se pudo procesar el pago.");
              }

              setStatus("success");
              setMessage("Pago enviado correctamente.");

              console.log("Stag Herd payment result:", data);
            } catch (error) {
              setStatus("error");
              setMessage(
                error instanceof Error ? error.message : "Error inesperado.",
              );
            }
          },

          onError: (error: unknown) => {
            console.error(error);
            setStatus("error");
            setMessage(
              "Mercado Pago devolvió un error al inicializar el formulario.",
            );
          },
        },
      });
    }

    initializeBrick().catch((error) => {
      console.error(error);
      setStatus("error");
      setMessage("No se pudo inicializar el checkout.");
    });
  }, [
    publicKey,
    amount,
    currency,
    externalReference,
    payerEmail,
    processUrl,
    csrfToken,
  ]);

  return (
    <section>
      <div style={{ marginBottom: 16 }}>
        <strong>Referencia:</strong> {externalReference}
      </div>

      <div id="cardPaymentBrick_container" />

      {status !== "idle" && (
        <div style={{ marginTop: 20 }}>
          <strong>Estado:</strong> {message}
        </div>
      )}
    </section>
  );
}
