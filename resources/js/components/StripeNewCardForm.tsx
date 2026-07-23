import { useEffect, useMemo, useRef, useState } from "react";

import {
  StripeSavedCardOption,
  type StripeSavedPaymentMethod,
} from "./stripe/StripeSavedCardOption";

import {
  StripeTokenizedCardCheckout,
  type StripeTokenizedCheckoutStatus,
} from "./stripe/StripeSavedCardPayment";
import { StripeCardCheckout } from "./stripe/StripeCardPayment";

export type { StripeSavedPaymentMethod } from "./stripe/StripeSavedCardOption";

type CheckoutMode = "saved_card" | "new_card";

type CheckoutStatus = StripeTokenizedCheckoutStatus | "loading";

type MetadataValue = string | number | boolean | null;

type Metadata = Record<string, MetadataValue>;

type Props = {
  publicKey: string;

  amount: number;
  currency: string;
  externalReference: string;

  payerReference?: string;
  payerEmail?: string;

  description?: string;

  paymentMethods: StripeSavedPaymentMethod[];

  defaultPaymentMethodId?: string | number | null;

  createIntentUrl: string;
  confirmIntentUrl: string;
  tokenizedCardUrl: string;

  csrfToken?: string;
  returnUrl?: string;

  metadata?: Metadata;

  existingCustomerId?: string | null;

  allowNewCard?: boolean;

  savedCardsTitle?: string;
  newCardLabel?: string;
  savedCardButtonText?: string;

  onPaymentMethodChange?: (paymentMethod: StripeSavedPaymentMethod) => void;

  onDeletePaymentMethod?: (
    paymentMethod: StripeSavedPaymentMethod,
  ) => void | Promise<void>;

  onSetDefaultPaymentMethod?: (
    paymentMethod: StripeSavedPaymentMethod,
  ) => void | Promise<void>;

  onModeChange?: (mode: CheckoutMode) => void;

  onStatusChange?: (status: CheckoutStatus, message?: string) => void;

  onSuccess?: (
    response: unknown,
    context: {
      mode: CheckoutMode;
      paymentMethod: StripeSavedPaymentMethod | null;
    },
  ) => void | Promise<void>;

  onError?: (error: unknown) => void;
};

function findDefaultPaymentMethod(
  paymentMethods: StripeSavedPaymentMethod[],
  defaultPaymentMethodId?: string | number | null,
): StripeSavedPaymentMethod | null {
  if (paymentMethods.length === 0) {
    return null;
  }

  if (defaultPaymentMethodId !== undefined && defaultPaymentMethodId !== null) {
    const explicitlySelected = paymentMethods.find(
      (paymentMethod) =>
        String(paymentMethod.id) === String(defaultPaymentMethodId),
    );

    if (explicitlySelected) {
      return explicitlySelected;
    }
  }

  const defaultCard = paymentMethods.find(
    (paymentMethod) => paymentMethod.isDefault,
  );

  return defaultCard ?? paymentMethods[0];
}

function resolvePaymentMethodId(
  paymentMethod?: StripeSavedPaymentMethod | null,
): string | null {
  if (!paymentMethod) {
    return null;
  }

  return String(paymentMethod.id);
}

export function StripeSavedCardsCheckout({
  publicKey,
  amount,
  currency,
  externalReference,
  payerReference,
  payerEmail,
  description = "Pago con Stripe",
  paymentMethods,
  defaultPaymentMethodId,
  createIntentUrl,
  confirmIntentUrl,
  tokenizedCardUrl,
  csrfToken,
  returnUrl,
  metadata = {},
  allowNewCard = true,
  existingCustomerId = null,
  savedCardsTitle = "Selecciona una tarjeta",
  newCardLabel = "Usar otra tarjeta",
  savedCardButtonText = "Pagar",
  onPaymentMethodChange,
  onDeletePaymentMethod,
  onSetDefaultPaymentMethod,
  onModeChange,
  onStatusChange,
  onSuccess,
  onError,
}: Props) {
  const initialPaymentMethod = useMemo(
    () => findDefaultPaymentMethod(paymentMethods, defaultPaymentMethodId),
    [paymentMethods, defaultPaymentMethodId],
  );

  const initialMode: CheckoutMode = initialPaymentMethod
    ? "saved_card"
    : "new_card";

  const [mode, setMode] = useState<CheckoutMode>(initialMode);

  const [selectedPaymentMethod, setSelectedPaymentMethod] =
    useState<StripeSavedPaymentMethod | null>(initialPaymentMethod);

  const [processing, setProcessing] = useState(false);

  const previousDefaultPaymentMethodIdRef = useRef<string | null>(
    resolvePaymentMethodId(initialPaymentMethod),
  );

  useEffect(() => {
    const resolvedDefaultPaymentMethod = findDefaultPaymentMethod(
      paymentMethods,
      defaultPaymentMethodId,
    );

    const resolvedDefaultPaymentMethodId = resolvePaymentMethodId(
      resolvedDefaultPaymentMethod,
    );

    const currentPaymentMethod = selectedPaymentMethod
      ? (paymentMethods.find(
          (paymentMethod) =>
            String(paymentMethod.id) === String(selectedPaymentMethod.id),
        ) ?? null)
      : null;

    const currentPaymentMethodId = resolvePaymentMethodId(currentPaymentMethod);

    const defaultChanged =
      previousDefaultPaymentMethodIdRef.current !==
      resolvedDefaultPaymentMethodId;

    const shouldSyncToDefault =
      defaultChanged &&
      resolvedDefaultPaymentMethodId !== null &&
      currentPaymentMethodId !== resolvedDefaultPaymentMethodId;

    const nextSelectedPaymentMethod =
      !currentPaymentMethod || shouldSyncToDefault
        ? resolvedDefaultPaymentMethod
        : currentPaymentMethod;

    const nextSelectedPaymentMethodId = resolvePaymentMethodId(
      nextSelectedPaymentMethod,
    );

    if (currentPaymentMethodId !== nextSelectedPaymentMethodId) {
      setSelectedPaymentMethod(nextSelectedPaymentMethod);

      if (nextSelectedPaymentMethod) {
        onPaymentMethodChange?.(nextSelectedPaymentMethod);
      }
    }

    previousDefaultPaymentMethodIdRef.current = resolvedDefaultPaymentMethodId;

    if (!nextSelectedPaymentMethod) {
      if (mode !== "new_card") {
        setMode("new_card");
        onModeChange?.("new_card");
      }

      return;
    }

    if (!allowNewCard && mode !== "saved_card") {
      setMode("saved_card");
      onModeChange?.("saved_card");
    }
  }, [
    paymentMethods,
    defaultPaymentMethodId,
    allowNewCard,
    mode,
    selectedPaymentMethod,
    onModeChange,
    onPaymentMethodChange,
  ]);

  function selectSavedCard(paymentMethod: StripeSavedPaymentMethod): void {
    if (processing) {
      return;
    }

    setSelectedPaymentMethod(paymentMethod);
    setMode("saved_card");

    onModeChange?.("saved_card");
    onPaymentMethodChange?.(paymentMethod);
  }

  function selectNewCard(): void {
    if (processing) {
      return;
    }

    setMode("new_card");
    onModeChange?.("new_card");
  }

  const hasSavedCards = paymentMethods.length > 0;

  return (
    <section className="w-full">
      {hasSavedCards && (
        <fieldset disabled={processing} className="m-0 min-w-0 border-0 p-0">
          <legend className="mb-3 block w-full text-sm font-semibold text-slate-800">
            {savedCardsTitle}
          </legend>

          <div className="space-y-3">
            {paymentMethods.map((paymentMethod) => (
              <StripeSavedCardOption
                key={paymentMethod.id}
                paymentMethod={paymentMethod}
                selected={
                  mode === "saved_card" &&
                  String(selectedPaymentMethod?.id ?? "") ===
                    String(paymentMethod.id)
                }
                disabled={processing}
                onSelect={selectSavedCard}
                onDelete={onDeletePaymentMethod}
                onSetDefault={onSetDefaultPaymentMethod}
              />
            ))}

            {allowNewCard && (
              <label
                htmlFor="stripe-new-card-option"
                className={[
                  "group flex w-full items-center gap-4 rounded-2xl border p-4",
                  "transition-all duration-200",
                  mode === "new_card"
                    ? "border-rose-400 bg-rose-50/60 shadow-sm ring-1 ring-rose-400"
                    : "border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50",
                  processing
                    ? "cursor-not-allowed opacity-60"
                    : "cursor-pointer",
                ].join(" ")}
              >
                <input
                  id="stripe-new-card-option"
                  type="radio"
                  name="stripe_saved_payment_method"
                  checked={mode === "new_card"}
                  disabled={processing}
                  onChange={selectNewCard}
                  className="sr-only"
                />

                <span
                  aria-hidden="true"
                  className={[
                    "flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2",
                    mode === "new_card"
                      ? "border-rose-500"
                      : "border-slate-300 group-hover:border-slate-400",
                  ].join(" ")}
                >
                  {mode === "new_card" && (
                    <span className="h-2.5 w-2.5 rounded-full bg-rose-500" />
                  )}
                </span>

                <span className="flex h-12 w-[68px] shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-slate-50">
                  <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    className="h-6 w-6 text-slate-500"
                    aria-hidden="true"
                  >
                    <rect
                      x="3"
                      y="5"
                      width="18"
                      height="14"
                      rx="2.5"
                      stroke="currentColor"
                      strokeWidth="1.7"
                    />

                    <path d="M3 9h18" stroke="currentColor" strokeWidth="1.7" />

                    <path
                      d="M16 14h2.5M17.25 12.75v2.5"
                      stroke="currentColor"
                      strokeWidth="1.7"
                      strokeLinecap="round"
                    />
                  </svg>
                </span>

                <span className="min-w-0 flex-1">
                  <span className="block text-sm font-semibold text-slate-900">
                    {newCardLabel}
                  </span>

                  <span className="mt-1 block text-xs leading-5 text-slate-500">
                    Captura los datos de una tarjeta diferente.
                  </span>
                </span>
              </label>
            )}
          </div>
        </fieldset>
      )}
      <div
        className={[
          hasSavedCards ? "mt-5" : "",
          mode === "new_card" ? "" : "",
          `[&_button]:min-h-12 [&_button]:w-full [&_button]:rounded-xl [&_button]:border-0 [&_button]:bg-[var(--color-primary)] [&_button]:px-5 [&_button]:py-3 [&_button]:text-sm [&_button]:font-semibold [&_button]:text-white [&_button]:shadow-sm [&_button]:transition-all [&_button:disabled]:cursor-not-allowed [&_button:disabled]:bg-slate-200 [&_button:disabled]:text-slate-500 [&_button:disabled]:shadow-none [&_button:focus-visible]:ring-2 [&_button:focus-visible]:ring-[var(--color-primary)] [&_button:focus-visible]:ring-offset-2 [&_button:focus-visible]:outline-none [&_button:hover:not(:disabled)]:opacity-90 [&_button:hover:not(:disabled)]:shadow-md [&_input:not([type='radio']):not([type='checkbox'])]:h-11 [&_input:not([type='radio']):not([type='checkbox'])]:w-full [&_input:not([type='radio']):not([type='checkbox'])]:rounded-xl [&_input:not([type='radio']):not([type='checkbox'])]:border [&_input:not([type='radio']):not([type='checkbox'])]:border-slate-300 [&_input:not([type='radio']):not([type='checkbox'])]:bg-white [&_input:not([type='radio']):not([type='checkbox'])]:px-3 [&_input:not([type='radio']):not([type='checkbox'])]:text-sm [&_input:not([type='radio']):not([type='checkbox'])]:text-slate-900 [&_input:not([type='radio']):not([type='checkbox'])]:outline-none [&_input:not([type='radio']):not([type='checkbox']):focus]:border-[var(--color-primary)] [&_input:not([type='radio']):not([type='checkbox']):focus]:ring-2 [&_input:not([type='radio']):not([type='checkbox']):focus]:ring-[color-mix(in_srgb,var(--color-primary)_20%,transparent)] [&_label]:text-sm [&_label]:font-medium [&_label]:text-slate-700 [&_p]:text-sm [&_p]:leading-5 [&_p]:text-slate-500 [&_select]:h-11 [&_select]:w-full [&_select]:rounded-xl [&_select]:border [&_select]:border-slate-300 [&_select]:bg-white [&_select]:px-3 [&_select]:text-sm [&_select]:text-slate-900 [&_select]:outline-none [&_select:focus]:border-[var(--color-primary)] [&_select:focus]:ring-2 [&_select:focus]:ring-[color-mix(in_srgb,var(--color-primary)_20%,transparent)]`,
        ].join(" ")}
      >
        {mode === "saved_card" && selectedPaymentMethod && (
          <StripeTokenizedCardCheckout
            publicKey={publicKey}
            amount={amount}
            currency={currency}
            externalReference={externalReference}
            customerId={selectedPaymentMethod.customerId}
            paymentMethodId={selectedPaymentMethod.paymentMethodId}
            payerReference={payerReference}
            payerEmail={payerEmail}
            description={description}
            processUrl={tokenizedCardUrl}
            confirmIntentUrl={confirmIntentUrl}
            csrfToken={csrfToken}
            returnUrl={returnUrl}
            metadata={metadata}
            buttonText={savedCardButtonText}
            onStatusChange={(status, message) => {
              setProcessing(
                status === "processing" || status === "authenticating",
              );

              onStatusChange?.(status, message);
            }}
            onSuccess={async (response) => {
              await onSuccess?.(response, {
                mode: "saved_card",
                paymentMethod: selectedPaymentMethod,
              });
            }}
            onError={onError}
          />
        )}

        {mode === "new_card" && (
          <StripeCardCheckout
            publicKey={publicKey}
            amount={amount}
            currency={currency}
            externalReference={externalReference}
            payerReference={payerReference}
            payerEmail={payerEmail}
            description={description}
            createIntentUrl={createIntentUrl}
            confirmIntentUrl={confirmIntentUrl}
            csrfToken={csrfToken}
            returnUrl={returnUrl}
            metadata={metadata}
            existingCustomerId={existingCustomerId}
            onStatusChange={(status, message) => {
              setProcessing(status === "loading" || status === "processing");

              onStatusChange?.(status, message);
            }}
            onSuccess={async (response) => {
              await onSuccess?.(response, {
                mode: "new_card",
                paymentMethod: null,
              });
            }}
            onError={onError}
          />
        )}
      </div>
    </section>
  );
}
