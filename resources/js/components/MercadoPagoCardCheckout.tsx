import React, { useCallback, useEffect, useId, useMemo, useRef, useState } from "react";
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

  orderLookupUrl?: string;
  completeUrl?: string;
  successUrlTemplate?: string;

  checkoutType?: string;
  action?: string;
  idOrder?: string;
  msisdn?: string;
  offerId?: string;
};

type CheckoutStatus = "idle" | "loading" | "success" | "error" | "challenge";

type MetadataValue = string | number | boolean | null;
type Metadata = Record<string, MetadataValue>;

type MercadoPagoOrderResponse = {
  ok?: boolean;
  message?: string;

  order_id?: string | null;
  order_status?: string | null;
  status_detail?: string | null;

  payment_id?: string | null;
  payment_status?: string | null;
  payment_status_detail?: string | null;

  next_action?: {
    type?: string;
    url?: string;
  } | null;

  payment?: any;
  raw?: any;
};

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

    if (value === undefined || value === null || value === "") {
      return;
    }

    metadata[key] = value;
  });

  return metadata;
}

function normalizeAmount(amount: number): string {
  if (!Number.isFinite(amount) || amount <= 0) {
    return "0.00";
  }

  return amount.toFixed(2);
}

function isApprovedMercadoPagoOrder(mp: MercadoPagoOrderResponse): boolean {
  const orderStatus = String(mp.order_status ?? "").toLowerCase();
  const paymentStatus = String(mp.payment_status ?? "").toLowerCase();
  const detail = String(mp.payment_status_detail ?? mp.status_detail ?? "").toLowerCase();

  return (
    (orderStatus === "processed" || paymentStatus === "processed" || paymentStatus === "approved") &&
    detail === "accredited"
  );
}

function isRejectedMercadoPagoOrder(mp: MercadoPagoOrderResponse): boolean {
  const orderStatus = String(mp.order_status ?? "").toLowerCase();
  const paymentStatus = String(mp.payment_status ?? "").toLowerCase();

  return ["failed", "canceled", "cancelled", "rejected"].includes(orderStatus)
    || ["failed", "canceled", "cancelled", "rejected"].includes(paymentStatus);
}

function getMercadoPagoChallengeUrl(mp: MercadoPagoOrderResponse): string | null {
  const url =
    mp.next_action?.url ??
    mp.raw?.transactions?.payments?.[0]?.payment_method?.transaction_security?.url ??
    mp.payment?.metadata?.mercado_pago_challenge_url ??
    null;

  return typeof url === "string" && url.trim() ? url : null;
}

function sleep(ms: number) {
  return new Promise((resolve) => window.setTimeout(resolve, ms));
}

function replaceSuccessUrlTemplate(template: string, completeResponse: any): string {
  const internalOrderId =
    completeResponse?.order?.id ??
    completeResponse?.order?.id_order ??
    completeResponse?.id_order ??
    completeResponse?.payment?.metadata?.id_order ??
    "";

  return template
    .replace("{id_order}", String(internalOrderId))
    .replace("{order_id}", String(internalOrderId));
}

async function parseJsonResponse(response: Response) {
  const responseText = await response.text();

  try {
    return responseText ? JSON.parse(responseText) : null;
  } catch {
    throw new Error(
      `El backend no devolvió JSON. Status ${response.status}. Respuesta: ${responseText.substring(0, 300)}`,
    );
  }
}

async function postJson<T>(
  url: string,
  payload: unknown,
  csrfToken: string,
  headers: Record<string, string> = {},
): Promise<T> {
  const response = await fetch(url, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
      "X-CSRF-TOKEN": csrfToken,
      "X-Requested-With": "XMLHttpRequest",
      ...headers,
    },
    credentials: "include",
    body: JSON.stringify(payload),
  });

  const data = await parseJsonResponse(response);

  if (!response.ok) {
    throw new Error(
      data?.message || data?.error || `No se pudo procesar el pago. Status ${response.status}`,
    );
  }

  return data as T;
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
  orderLookupUrl,
  completeUrl,
  successUrlTemplate,
  checkoutType,
  action,
  idOrder,
  msisdn,
  offerId,
}: Props) {
  const reactId = useId();
  const containerId = `stag-herd-card-payment-brick-${reactId.replace(/:/g, "")}`;

  const controllerRef = useRef<any>(null);
  const lockRef = useRef(false);
  const challengeLockRef = useRef(false);

  const [status, setStatus] = useState<CheckoutStatus>("idle");
  const [message, setMessage] = useState<string>("");
  const [responsePayload, setResponsePayload] = useState<unknown>(null);
  const [challengeUrl, setChallengeUrl] = useState<string | null>(null);
  const [challengeOrderId, setChallengeOrderId] = useState<string | null>(null);

  const amountNormalized = useMemo(() => normalizeAmount(amount), [amount]);

  const destroyBrick = useCallback(async () => {
    try {
      if (controllerRef.current?.unmount) {
        await controllerRef.current.unmount();
      }
    } catch (error) {
      console.warn("No se pudo desmontar Mercado Pago Brick", error);
    } finally {
      controllerRef.current = null;
    }
  }, []);

  const orderLookupUrlFor = useCallback(
    (orderId: string) => {
      if (orderLookupUrl) {
        return orderLookupUrl.replace("{order_id}", encodeURIComponent(orderId));
      }

      return `/stag-herd/payments/mercado-pago/orders/${encodeURIComponent(orderId)}`;
    },
    [orderLookupUrl],
  );

  const getMercadoPagoOrder = useCallback(
    async (orderId: string): Promise<MercadoPagoOrderResponse> => {
      const response = await fetch(orderLookupUrlFor(orderId), {
        headers: {
          Accept: "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
        credentials: "include",
      });

      const data = await parseJsonResponse(response);

      if (!response.ok) {
        throw new Error(data?.message || data?.error || `Error HTTP ${response.status}`);
      }

      return data as MercadoPagoOrderResponse;
    },
    [orderLookupUrlFor],
  );

  const waitForMercadoPagoOrderApproval = useCallback(
    async (orderId: string): Promise<MercadoPagoOrderResponse> => {
      let latest: MercadoPagoOrderResponse | null = null;

      for (let attempt = 0; attempt < 10; attempt += 1) {
        latest = await getMercadoPagoOrder(orderId);

        if (isApprovedMercadoPagoOrder(latest)) {
          return latest;
        }

        if (isRejectedMercadoPagoOrder(latest)) {
          throw new Error(
            `Pago no aprobado (${latest.payment_status ?? latest.order_status ?? "sin status"} / ${latest.payment_status_detail ?? latest.status_detail ?? "sin detalle"})`,
          );
        }

        await sleep(1500);
      }

      throw new Error(
        `Mercado Pago no confirmó el pago (${latest?.payment_status ?? latest?.order_status ?? "sin status"} / ${latest?.payment_status_detail ?? latest?.status_detail ?? "sin detalle"}).`,
      );
    },
    [getMercadoPagoOrder],
  );

  const completeHostCheckout = useCallback(
    async (mpPayment: MercadoPagoOrderResponse, metadata: Metadata) => {
      if (!completeUrl) {
        window.dispatchEvent(
          new CustomEvent("stag-herd:mercado-pago-approved", {
            detail: mpPayment,
          }),
        );

        return null;
      }

      if (!mpPayment.payment_id) {
        throw new Error("Mercado Pago no regresó payment_id.");
      }

      const completeResponse = await postJson<any>(
        completeUrl,
        {
          payment_id: mpPayment.payment_id,
          payment_method: "MERCADOPAGO",

          mp_order_id: mpPayment.order_id,
          mp_status: mpPayment.payment_status,
          mp_status_detail: mpPayment.payment_status_detail ?? mpPayment.status_detail,

          amount: amountNormalized,
          currency,
          external_reference: externalReference,

          checkout_type: checkoutType ?? metadata.checkout_type ?? null,
          action: action ?? metadata.action ?? null,
          id_order: idOrder ?? metadata.id_order ?? null,
          msisdn: msisdn ?? metadata.msisdn ?? null,
          offer_id: offerId ?? metadata.offer_id ?? null,

          metadata,
        },
        csrfToken,
      );

      if (successUrlTemplate) {
        const redirectUrl = replaceSuccessUrlTemplate(successUrlTemplate, completeResponse);

        if (redirectUrl && redirectUrl !== successUrlTemplate) {
          window.location.href = redirectUrl;
        }
      }

      return completeResponse;
    },
    [
      action,
      amountNormalized,
      checkoutType,
      completeUrl,
      csrfToken,
      currency,
      externalReference,
      idOrder,
      msisdn,
      offerId,
      successUrlTemplate,
    ],
  );

  const handleApprovedPayment = useCallback(
    async (mpPayment: MercadoPagoOrderResponse, metadata: Metadata) => {
      const completeResponse = await completeHostCheckout(mpPayment, metadata);

      setStatus("success");
      setMessage("Pago aprobado correctamente.");
      setResponsePayload({ mercado_pago: mpPayment, complete_response: completeResponse });

      return { mercado_pago: mpPayment, complete_response: completeResponse };
    },
    [completeHostCheckout],
  );

  const verifyChallengeResult = useCallback(async () => {
    if (!challengeOrderId || challengeLockRef.current) {
      return;
    }

    challengeLockRef.current = true;
    setStatus("loading");
    setMessage("Verificando pago con Mercado Pago...");

    try {
      const metadata = readMetadataFromPage();
      const approvedOrder = await waitForMercadoPagoOrderApproval(challengeOrderId);

      await handleApprovedPayment(approvedOrder, metadata);
      setChallengeUrl(null);
      setChallengeOrderId(null);
    } catch (error) {
      const errorMessage = error instanceof Error
        ? error.message
        : "No se pudo confirmar la autenticación de Mercado Pago.";

      setStatus("error");
      setMessage(errorMessage);
      setResponsePayload(error);
    } finally {
      challengeLockRef.current = false;
    }
  }, [challengeOrderId, handleApprovedPayment, waitForMercadoPagoOrderApproval]);

  useEffect(() => {
    if (!challengeUrl || !challengeOrderId) {
      return;
    }

    const handler = (event: MessageEvent) => {
      const eventStatus = (event.data as any)?.status;

      if (eventStatus === "COMPLETE") {
        void verifyChallengeResult();
      }
    };

    window.addEventListener("message", handler);

    return () => window.removeEventListener("message", handler);
  }, [challengeUrl, challengeOrderId, verifyChallengeResult]);

  useEffect(() => {
    if (window.MP_DEVICE_SESSION_ID) {
      return;
    }

    const existingScript = document.querySelector(
      'script[src="https://www.mercadopago.com/v2/security.js"]',
    );

    if (existingScript) {
      return;
    }

    const script = document.createElement("script");
    script.src = "https://www.mercadopago.com/v2/security.js";
    script.async = true;
    script.setAttribute("view", "checkout");

    document.body.appendChild(script);
  }, []);

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
              minInstallments: 1,
              maxInstallments: 1,
            },
          },

          callbacks: {
            onReady: () => {
              setStatus("idle");
              setMessage("Checkout listo.");
            },

            onSubmit: async (cardFormData: any, additionalData: any) => {
              if (lockRef.current) {
                return;
              }

              lockRef.current = true;
              setStatus("loading");
              setMessage("Procesando pago...");
              setResponsePayload(null);
              setChallengeUrl(null);
              setChallengeOrderId(null);

              const currentAmount = Number(readInputValue("amount") || amount);
              const currentCurrency = readInputValue("currency") || currency;

              const currentExternalReference =
                readInputValue("external_reference") ||
                externalReference ||
                `BRICK-${Date.now()}`;

              const currentDescription = readInputValue("description") || description;

              const resolvedPayerEmail =
                cardFormData?.payer?.email ||
                readInputValue("payer_email") ||
                payerEmail ||
                "cliente@test.com";

              const metadata = {
                ...readMetadataFromPage(),
                ...(checkoutType ? { checkout_type: checkoutType } : {}),
                ...(action ? { action } : {}),
                ...(idOrder ? { id_order: idOrder } : {}),
                ...(msisdn ? { msisdn } : {}),
                ...(offerId ? { offer_id: offerId } : {}),
              };

              const idempotencyKey = crypto.randomUUID().slice(0, 64);
              const deviceId = window.MP_DEVICE_SESSION_ID ?? null;

              const payload = {
                provider: "mercado_pago",
                method: "card",

                amount: currentAmount,
                currency: currentCurrency,

                external_reference: currentExternalReference,
                payer_email: resolvedPayerEmail,
                description: currentDescription,
                device_id: deviceId,

                metadata,

                mercado_pago: {
                  token: cardFormData?.token,
                  payment_method_id: cardFormData?.payment_method_id,
                  payment_type_id:
                    additionalData?.paymentTypeId ??
                    cardFormData?.payment_type_id ??
                    "credit_card",
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
                const data = await postJson<MercadoPagoOrderResponse>(
                  processUrl,
                  payload,
                  csrfToken,
                  {
                    "X-Idempotency-Key": idempotencyKey,
                  },
                );

                if (isApprovedMercadoPagoOrder(data)) {
                  return await handleApprovedPayment(data, metadata);
                }

                const nextChallengeUrl = getMercadoPagoChallengeUrl(data);

                if (data.order_id && nextChallengeUrl) {
                  await destroyBrick();
                  setStatus("challenge");
                  setMessage("Completa la autenticación bancaria para confirmar el pago.");
                  setChallengeUrl(nextChallengeUrl);
                  setChallengeOrderId(data.order_id);
                  setResponsePayload(data);

                  return data;
                }

                throw new Error(
                  `Pago no aprobado (${data.payment_status ?? data.order_status ?? "sin status"} / ${data.payment_status_detail ?? data.status_detail ?? "sin detalle"})`,
                );
              } catch (error) {
                const errorMessage = error instanceof Error
                  ? error.message
                  : "Error inesperado al procesar el pago.";

                setStatus("error");
                setMessage(errorMessage);
                setResponsePayload(error);

                throw error;
              } finally {
                lockRef.current = false;
              }
            },

            onError: (error: unknown) => {
              console.error(error);

              setStatus("error");
              setMessage("Mercado Pago devolvió un error al inicializar el formulario.");
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
      void destroyBrick();
    };
  }, [
    action,
    amount,
    checkoutType,
    completeHostCheckout,
    containerId,
    csrfToken,
    currency,
    description,
    destroyBrick,
    externalReference,
    handleApprovedPayment,
    idOrder,
    msisdn,
    offerId,
    payerEmail,
    processUrl,
    publicKey,
  ]);

  return (
    <section>
      <div style={{ marginBottom: 16 }}>
        <strong>Referencia:</strong> {externalReference || "Se tomará del formulario"}
        <br />
        <strong>Monto:</strong> ${amountNormalized} {currency}
        <br />
        <strong>Email:</strong> {payerEmail || "cliente@test.com"}
      </div>

      {challengeUrl ? (
        <div style={{ marginTop: 16 }}>
          <p>Completa la validación de tu banco para continuar.</p>

          <iframe
            title="Validación bancaria Mercado Pago"
            src={challengeUrl}
            style={{
              width: "100%",
              minHeight: 460,
              border: "1px solid #e5e7eb",
              borderRadius: 12,
            }}
            allow="payment *"
            sandbox="allow-forms allow-popups allow-same-origin allow-scripts"
          />

          <button
            type="button"
            onClick={() => void verifyChallengeResult()}
            disabled={status === "loading"}
            style={{ marginTop: 12 }}
          >
            Verificar pago
          </button>
        </div>
      ) : (
        <div id={containerId} />
      )}

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
