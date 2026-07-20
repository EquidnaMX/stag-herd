import {
  PaymentRequestButtonElement,
  useStripe,
} from "@stripe/react-stripe-js";
import { useEffect, useMemo, useState } from "react";

type WalletBackendStartResponse = {
  clientSecret: string;
  providerResponse: any;
  providerStatus?: string | null;
};

type WalletSuccessResponse = {
  providerResponse: any;
};

type Props = {
  amount: number;
  currency: string;
  country: string;
  label: string;
  requestPayerName?: boolean;
  requestPayerEmail?: boolean;
  requestShipping?: boolean;
  disabled?: boolean;
  onPaymentMethod: (event: {
    paymentMethodId: string;
    payerName?: string | null;
    payerEmail?: string | null;
    wallet: "google_pay";
    rawEvent: unknown;
  }) => Promise<WalletBackendStartResponse>;
  onSuccess?: (result: WalletSuccessResponse) => void | Promise<void>;
  onAvailabilityChange?: (available: boolean) => void;
  onError?: (error: unknown) => void;
};

function normalizeStatus(status: unknown): string {
  return String(status ?? "").toLowerCase();
}

function isFinalStatus(status: string): boolean {
  return ["succeeded", "processing", "requires_capture"].includes(status);
}

export function GooglePayButton({
  amount,
  currency,
  country,
  label,
  requestPayerName = false,
  requestPayerEmail = false,
  requestShipping = false,
  disabled = false,
  onPaymentMethod,
  onSuccess,
  onAvailabilityChange,
  onError,
}: Props) {
  const stripe = useStripe();
  const [paymentRequest, setPaymentRequest] = useState<any>(null);
  const [available, setAvailable] = useState(false);

  const normalizedCurrency = useMemo(() => currency.toLowerCase(), [currency]);
  const normalizedCountry = useMemo(() => country.toUpperCase(), [country]);

  useEffect(() => {
    if (!stripe || disabled) {
      setPaymentRequest(null);
      setAvailable(false);
      onAvailabilityChange?.(false);
      return;
    }

    const pr = stripe.paymentRequest({
      country: normalizedCountry,
      currency: normalizedCurrency,
      total: {
        label,
        amount,
      },
      requestPayerName,
      requestPayerEmail,
      requestShipping,
    });

    let mounted = true;

    pr.canMakePayment()
      .then((result) => {
        const isAvailable = Boolean(result?.googlePay);

        if (!mounted) return;

        if (!isAvailable) {
          setPaymentRequest(null);
          setAvailable(false);
          onAvailabilityChange?.(false);
          return;
        }

        pr.on("paymentmethod", async (event: any) => {
          try {
            const start = await onPaymentMethod({
              paymentMethodId: event.paymentMethod.id,
              payerName: event.payerName ?? null,
              payerEmail: event.payerEmail ?? null,
              wallet: "google_pay",
              rawEvent: event,
            });

            const clientSecret = start?.clientSecret;
            const status = normalizeStatus(
              start?.providerStatus ??
                start?.providerResponse?.provider_status ??
                start?.providerResponse?.payment?.provider_status ??
                start?.providerResponse?.status ??
                start?.providerResponse?.payment?.status,
            );

            if (!clientSecret || typeof clientSecret !== "string") {
              event.complete("fail");
              throw new Error(
                "El backend no regresó client_secret para Google Pay.",
              );
            }

            if (status === "requires_action") {
              event.complete("success");

              const actionResult = await stripe.handleNextAction({
                clientSecret,
              });

              if (actionResult.error) {
                throw new Error(
                  actionResult.error.message ||
                    "No se pudo completar la autenticación de Google Pay.",
                );
              }

              const finalStatus = normalizeStatus(
                actionResult.paymentIntent?.status,
              );

              if (!isFinalStatus(finalStatus)) {
                throw new Error(
                  `Stripe no confirmó Google Pay. Estado: ${finalStatus}.`,
                );
              }

              await onSuccess?.({
                providerResponse: {
                  ...start.providerResponse,
                  payment_intent_id:
                    actionResult.paymentIntent?.id ??
                    start.providerResponse?.payment_intent_id,
                  provider_status: finalStatus,
                  status: finalStatus,
                },
              });

              return;
            }

            if (!isFinalStatus(status)) {
              event.complete("fail");
              throw new Error(
                `Stripe no confirmó Google Pay. Estado: ${status}.`,
              );
            }

            event.complete("success");

            await onSuccess?.({
              providerResponse: start.providerResponse,
            });
          } catch (error) {
            onError?.(error);
          }
        });

        setPaymentRequest(pr);
        setAvailable(true);
        onAvailabilityChange?.(true);
      })
      .catch((error) => {
        if (!mounted) return;

        setPaymentRequest(null);
        setAvailable(false);
        onAvailabilityChange?.(false);
        onError?.(error);
      });

    return () => {
      mounted = false;
    };
  }, [
    stripe,
    amount,
    normalizedCurrency,
    normalizedCountry,
    label,
    requestPayerName,
    requestPayerEmail,
    requestShipping,
    disabled,
    onPaymentMethod,
    onSuccess,
    onAvailabilityChange,
    onError,
  ]);

  if (!paymentRequest || !available) {
    return null;
  }

  return (
    <PaymentRequestButtonElement
      options={{
        paymentRequest,
        style: {
          paymentRequestButton: {
            type: "buy",
            theme: "dark",
            height: "48px",
          },
        },
      }}
    />
  );
}
