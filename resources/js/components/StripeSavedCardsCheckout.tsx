import {
  useEffect,
  useMemo,
  useState,
} from "react";

import { StripeCardCheckout } from "./StripeCardCheckout";

import {
  StripeSavedCardOption,
  StripeSavedPaymentMethod,
} from "./stripe/StripeSavedCardOption";

import {
  StripeTokenizedCardCheckout,
  StripeTokenizedCheckoutStatus,
} from "./stripe/StripeTokenizedCardCheckout";

export type {
  StripeSavedPaymentMethod,
} from "./stripe/StripeSavedCardOption";

type CheckoutMode =
  | "saved_card"
  | "new_card";

type CheckoutStatus =
  | StripeTokenizedCheckoutStatus
  | "loading";

type MetadataValue =
  | string
  | number
  | boolean
  | null;

type Metadata = Record<
  string,
  MetadataValue
>;

type Props = {
  publicKey: string;

  amount: number;
  currency: string;
  externalReference: string;

  payerReference?: string;
  payerEmail?: string;

  description?: string;

  /**
   * Tarjetas obtenidas desde la base de datos del host.
   */
  paymentMethods:
    StripeSavedPaymentMethod[];

  /**
   * Identificador local de la tarjeta que debe
   * aparecer seleccionada.
   */
  defaultPaymentMethodId?:
    | string
    | number
    | null;

  /**
   * URLs para tarjeta nueva.
   */
  createIntentUrl: string;
  confirmIntentUrl: string;

  /**
   * URL para tarjeta guardada.
   */
  tokenizedCardUrl: string;

  csrfToken?: string;
  returnUrl?: string;

  metadata?: Metadata;

  /**
   * Permite mostrar la opción de capturar otra tarjeta.
   */
  allowNewCard?: boolean;

  savedCardsTitle?: string;
  newCardLabel?: string;
  savedCardButtonText?: string;

  onPaymentMethodChange?: (
    paymentMethod:
      StripeSavedPaymentMethod,
  ) => void;

  onModeChange?: (
    mode: CheckoutMode,
  ) => void;

  onStatusChange?: (
    status: CheckoutStatus,
    message?: string,
  ) => void;

  onSuccess?: (
    response: unknown,
    context: {
      mode: CheckoutMode;
      paymentMethod:
        | StripeSavedPaymentMethod
        | null;
    },
  ) => void | Promise<void>;

  onError?: (
    error: unknown,
  ) => void;
};

function findDefaultPaymentMethod(
  paymentMethods:
    StripeSavedPaymentMethod[],

  defaultPaymentMethodId?:
    | string
    | number
    | null,
): StripeSavedPaymentMethod | null {
  if (
    paymentMethods.length === 0
  ) {
    return null;
  }

  if (
    defaultPaymentMethodId !==
      undefined &&
    defaultPaymentMethodId !==
      null
  ) {
    const explicitlySelected =
      paymentMethods.find(
        (paymentMethod) =>
          String(
            paymentMethod.id,
          ) ===
          String(
            defaultPaymentMethodId,
          ),
      );

    if (explicitlySelected) {
      return explicitlySelected;
    }
  }

  const defaultCard =
    paymentMethods.find(
      (paymentMethod) =>
        paymentMethod.isDefault,
    );

  return (
    defaultCard ??
    paymentMethods[0]
  );
}

export function StripeSavedCardsCheckout({
  publicKey,
  amount,
  currency,
  externalReference,
  payerReference,
  payerEmail,
  description =
    "Pago con Stripe",
  paymentMethods,
  defaultPaymentMethodId,
  createIntentUrl,
  confirmIntentUrl,
  tokenizedCardUrl,
  csrfToken,
  returnUrl,
  metadata = {},
  allowNewCard = true,
  savedCardsTitle =
    "Selecciona una tarjeta",
  newCardLabel =
    "Usar otra tarjeta",
  savedCardButtonText = "Pagar",
  onPaymentMethodChange,
  onModeChange,
  onStatusChange,
  onSuccess,
  onError,
}: Props) {
  const initialPaymentMethod =
    useMemo(
      () =>
        findDefaultPaymentMethod(
          paymentMethods,
          defaultPaymentMethodId,
        ),
      [
        paymentMethods,
        defaultPaymentMethodId,
      ],
    );

  const initialMode:
    CheckoutMode =
    initialPaymentMethod
      ? "saved_card"
      : "new_card";

  const [mode, setMode] =
    useState<CheckoutMode>(
      initialMode,
    );

  const [
    selectedPaymentMethod,
    setSelectedPaymentMethod,
  ] = useState<
    StripeSavedPaymentMethod | null
  >(initialPaymentMethod);

  const [processing, setProcessing] =
    useState(false);

  useEffect(() => {
    const resolved =
      findDefaultPaymentMethod(
        paymentMethods,
        defaultPaymentMethodId,
      );

    setSelectedPaymentMethod(
      resolved,
    );

    if (!resolved) {
      setMode("new_card");

      onModeChange?.(
        "new_card",
      );

      return;
    }

    if (
      mode === "saved_card"
    ) {
      return;
    }

    if (!allowNewCard) {
      setMode("saved_card");

      onModeChange?.(
        "saved_card",
      );
    }
  }, [
    paymentMethods,
    defaultPaymentMethodId,
    allowNewCard,
  ]);

  function selectSavedCard(
    paymentMethod:
      StripeSavedPaymentMethod,
  ): void {
    setSelectedPaymentMethod(
      paymentMethod,
    );

    setMode("saved_card");

    onModeChange?.(
      "saved_card",
    );

    onPaymentMethodChange?.(
      paymentMethod,
    );
  }

  function selectNewCard(): void {
    setMode("new_card");

    onModeChange?.(
      "new_card",
    );
  }

  const hasSavedCards =
    paymentMethods.length > 0;

  return (
    <section>
      {hasSavedCards && (
        <fieldset
          disabled={processing}
        >
          <legend>
            {savedCardsTitle}
          </legend>

          {paymentMethods.map(
            (paymentMethod) => (
              <div
                key={
                  paymentMethod.id
                }
              >
                <StripeSavedCardOption
                  paymentMethod={
                    paymentMethod
                  }
                  selected={
                    mode ===
                      "saved_card" &&
                    String(
                      selectedPaymentMethod
                        ?.id ?? "",
                    ) ===
                      String(
                        paymentMethod.id,
                      )
                  }
                  disabled={
                    processing
                  }
                  onSelect={
                    selectSavedCard
                  }
                />
              </div>
            ),
          )}

          {allowNewCard && (
            <div>
              <label htmlFor="stripe-new-card-option">
                <input
                  id="stripe-new-card-option"
                  type="radio"
                  name="stripe_saved_payment_method"
                  checked={
                    mode ===
                    "new_card"
                  }
                  disabled={
                    processing
                  }
                  onChange={
                    selectNewCard
                  }
                />

                <span>
                  {newCardLabel}
                </span>
              </label>
            </div>
          )}
        </fieldset>
      )}

      {mode === "saved_card" &&
        selectedPaymentMethod && (
          <StripeTokenizedCardCheckout
            publicKey={
              publicKey
            }
            amount={amount}
            currency={currency}
            externalReference={
              externalReference
            }
            customerId={
              selectedPaymentMethod
                .customerId
            }
            paymentMethodId={
              selectedPaymentMethod
                .paymentMethodId
            }
            payerReference={
              payerReference
            }
            payerEmail={
              payerEmail
            }
            description={
              description
            }
            processUrl={
              tokenizedCardUrl
            }
            confirmIntentUrl={
              confirmIntentUrl
            }
            csrfToken={
              csrfToken
            }
            returnUrl={
              returnUrl
            }
            metadata={
              metadata
            }
            buttonText={
              savedCardButtonText
            }
            onStatusChange={(
              status,
              message,
            ) => {
              setProcessing(
                status ===
                  "processing" ||
                  status ===
                    "authenticating",
              );

              onStatusChange?.(
                status,
                message,
              );
            }}
            onSuccess={async (
              response,
            ) => {
              await onSuccess?.(
                response,
                {
                  mode:
                    "saved_card",

                  paymentMethod:
                    selectedPaymentMethod,
                },
              );
            }}
            onError={onError}
          />
        )}

      {mode === "new_card" && (
        <StripeCardCheckout
          publicKey={publicKey}
          amount={amount}
          currency={currency}
          externalReference={
            externalReference
          }
          payerEmail={
            payerEmail
          }
          description={
            description
          }
          createIntentUrl={
            createIntentUrl
          }
          confirmIntentUrl={
            confirmIntentUrl
          }
          csrfToken={
            csrfToken
          }
          returnUrl={
            returnUrl
          }
          onStatusChange={(
            status,
            message,
          ) => {
            setProcessing(
              status ===
                "processing",
            );

            onStatusChange?.(
              status,
              message,
            );
          }}
          onSuccess={async (
            response,
          ) => {
            await onSuccess?.(
              response,
              {
                mode:
                  "new_card",

                paymentMethod:
                  null,
              },
            );
          }}
          onError={onError}
        />
      )}
    </section>
  );
}