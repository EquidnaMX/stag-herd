import {
  PaymentRequestButtonElement,
  useStripe,
} from "@stripe/react-stripe-js";
import { useEffect, useMemo, useState } from "react";

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
  }) => Promise<{ success: boolean; error?: string }>;
  onAvailabilityChange?: (available: boolean) => void;
  onError?: (error: unknown) => void;
};

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
            const response = await onPaymentMethod({
              paymentMethodId: event.paymentMethod.id,
              payerName: event.payerName ?? null,
              payerEmail: event.payerEmail ?? null,
              wallet: "google_pay",
              rawEvent: event,
            });

            if (response.success) {
              event.complete("success");
            } else {
              event.complete("fail");
              if (response.error) {
                throw new Error(response.error);
              }
            }
          } catch (error) {
            event.complete("fail");
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
