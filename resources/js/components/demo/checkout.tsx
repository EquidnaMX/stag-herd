import React from "react";
import { createRoot } from "react-dom/client";

import { MercadoPagoCardCheckout } from "../MercadoPagoCardCheckout";
import { PayPalCheckout } from "../PayPalCheckout";
import { StripeCardCheckout } from "./components/StripeCardCheckout";

import { StripeSaveCard, StripeSavedCardResult } from "../StripeSaveCard";

import {
  StripeSavedCardsCheckout,
  StripeSavedPaymentMethod,
} from "./components/StripeSavedCardsCheckout";

function parseBoolean(value: string | undefined, fallback = false): boolean {
  if (value === undefined || value === "") {
    return fallback;
  }

  return ["true", "1", "yes"].includes(value.toLowerCase());
}

function parseJson<T>(value: string | undefined, fallback: T): T {
  if (!value) {
    return fallback;
  }

  try {
    return JSON.parse(value) as T;
  } catch (error) {
    console.error("No se pudo interpretar JSON desde data-*:", error);

    return fallback;
  }
}

function dispatchCheckoutEvent(
  element: HTMLElement,
  eventName: string,
  detail: unknown,
): void {
  element.dispatchEvent(
    new CustomEvent(eventName, {
      detail,
      bubbles: true,
    }),
  );
}

/*
|--------------------------------------------------------------------------
| Mercado Pago
|--------------------------------------------------------------------------
*/

document
  .querySelectorAll<HTMLElement>(
    "[data-stag-herd-checkout='mercado-pago-card']",
  )
  .forEach((element) => {
    createRoot(element).render(
      <MercadoPagoCardCheckout
        publicKey={element.dataset.publicKey ?? ""}
        amount={Number(element.dataset.amount ?? 0)}
        currency={element.dataset.currency ?? "MXN"}
        externalReference={element.dataset.externalReference ?? ""}
        payerEmail={element.dataset.payerEmail ?? "cliente@test.com"}
        processUrl={element.dataset.processUrl ?? ""}
        csrfToken={element.dataset.csrfToken ?? ""}
        description={
          element.dataset.description ?? "Pago desde Mercado Pago Card Brick"
        }
      />,
    );
  });

/*
|--------------------------------------------------------------------------
| PayPal
|--------------------------------------------------------------------------
*/

document
  .querySelectorAll<HTMLElement>("[data-stag-herd-checkout='paypal']")
  .forEach((element) => {
    createRoot(element).render(
      <PayPalCheckout
        clientId={element.dataset.clientId ?? ""}
        amount={Number(element.dataset.amount ?? 0)}
        currency={element.dataset.currency ?? "MXN"}
        externalReference={element.dataset.externalReference ?? ""}
        payerEmail={element.dataset.payerEmail ?? "cliente@test.com"}
        description={
          element.dataset.description ?? "Pago desde PayPal Checkout"
        }
        createOrderUrl={element.dataset.createOrderUrl ?? ""}
        captureOrderUrl={element.dataset.captureOrderUrl ?? ""}
        csrfToken={element.dataset.csrfToken ?? ""}
      />,
    );
  });

/*
|--------------------------------------------------------------------------
| Stripe: tarjeta nueva
|--------------------------------------------------------------------------
*/

document
  .querySelectorAll<HTMLElement>("[data-stag-herd-checkout='stripe-card']")
  .forEach((element) => {
    createRoot(element).render(
      <StripeCardCheckout
        publicKey={element.dataset.publicKey ?? ""}
        amount={Number(element.dataset.amount ?? 0)}
        currency={element.dataset.currency ?? "MXN"}
        externalReference={element.dataset.externalReference ?? ""}
        payerEmail={element.dataset.payerEmail ?? "cliente@test.com"}
        description={element.dataset.description ?? "Pago desde Stripe Card"}
        createIntentUrl={element.dataset.createIntentUrl ?? ""}
        confirmIntentUrl={element.dataset.confirmIntentUrl ?? ""}
        csrfToken={element.dataset.csrfToken ?? ""}
        returnUrl={element.dataset.returnUrl ?? ""}
      />,
    );
  });

/*
|--------------------------------------------------------------------------
| Stripe: guardar tarjeta
|--------------------------------------------------------------------------
*/

document
  .querySelectorAll<HTMLElement>("[data-stag-herd-checkout='stripe-save-card']")
  .forEach((element) => {
    createRoot(element).render(
      <StripeSaveCard
        publicKey={element.dataset.publicKey ?? ""}
        payerReference={element.dataset.payerReference ?? ""}
        payerEmail={element.dataset.payerEmail}
        payerName={element.dataset.payerName}
        customerId={element.dataset.customerId || undefined}
        createSetupUrl={element.dataset.createSetupUrl ?? ""}
        completeSetupUrl={element.dataset.completeSetupUrl ?? ""}
        csrfToken={element.dataset.csrfToken ?? ""}
        returnUrl={element.dataset.returnUrl}
        buttonText={element.dataset.buttonText ?? "Guardar tarjeta"}
        onStatusChange={(status, message) => {
          dispatchCheckoutEvent(element, "stag-herd:stripe-save-card-status", {
            status,
            message,
          });
        }}
        onSuccess={(result: StripeSavedCardResult, response) => {
          dispatchCheckoutEvent(element, "stag-herd:stripe-card-saved", {
            result,
            response,
          });
        }}
        onError={(error) => {
          dispatchCheckoutEvent(element, "stag-herd:stripe-save-card-error", {
            error,
          });
        }}
      />,
    );
  });

/*
|--------------------------------------------------------------------------
| Stripe: checkout con tarjetas guardadas
|--------------------------------------------------------------------------
*/

document
  .querySelectorAll<HTMLElement>(
    "[data-stag-herd-checkout='stripe-saved-cards']",
  )
  .forEach((element) => {
    const paymentMethods = parseJson<StripeSavedPaymentMethod[]>(
      element.dataset.paymentMethods,
      [],
    );

    createRoot(element).render(
      <StripeSavedCardsCheckout
        publicKey={element.dataset.publicKey ?? ""}
        amount={Number(element.dataset.amount ?? 0)}
        currency={element.dataset.currency ?? "MXN"}
        externalReference={element.dataset.externalReference ?? ""}
        payerReference={element.dataset.payerReference}
        payerEmail={element.dataset.payerEmail}
        description={element.dataset.description ?? "Pago con Stripe"}
        paymentMethods={paymentMethods}
        defaultPaymentMethodId={element.dataset.defaultPaymentMethodId}
        createIntentUrl={element.dataset.createIntentUrl ?? ""}
        confirmIntentUrl={element.dataset.confirmIntentUrl ?? ""}
        tokenizedCardUrl={element.dataset.tokenizedCardUrl ?? ""}
        csrfToken={element.dataset.csrfToken ?? ""}
        returnUrl={element.dataset.returnUrl}
        allowNewCard={parseBoolean(element.dataset.allowNewCard, true)}
        savedCardsTitle={
          element.dataset.savedCardsTitle ?? "Selecciona una tarjeta"
        }
        newCardLabel={element.dataset.newCardLabel ?? "Usar otra tarjeta"}
        savedCardButtonText={element.dataset.buttonText ?? "Pagar"}
        onPaymentMethodChange={(paymentMethod) => {
          dispatchCheckoutEvent(
            element,
            "stag-herd:stripe-payment-method-change",
            {
              paymentMethod,
            },
          );
        }}
        onModeChange={(mode) => {
          dispatchCheckoutEvent(
            element,
            "stag-herd:stripe-checkout-mode-change",
            {
              mode,
            },
          );
        }}
        onStatusChange={(status, message) => {
          dispatchCheckoutEvent(element, "stag-herd:stripe-checkout-status", {
            status,
            message,
          });
        }}
        onSuccess={(response, context) => {
          dispatchCheckoutEvent(element, "stag-herd:stripe-checkout-success", {
            response,
            context,
          });
        }}
        onError={(error) => {
          dispatchCheckoutEvent(element, "stag-herd:stripe-checkout-error", {
            error,
          });
        }}
      />,
    );
  });
