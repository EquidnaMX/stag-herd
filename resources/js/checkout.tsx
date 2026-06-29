import React from "react";
import { createRoot } from "react-dom/client";
import { MercadoPagoCardCheckout } from "./components/MercadoPagoCardCheckout";
import { PayPalCheckout } from "./components/PayPalCheckout";
import { StripeCardCheckout } from "./components/StripeCardCheckout";

const mercadoPagoElements = document.querySelectorAll<HTMLElement>(
  "[data-stag-herd-checkout='mercado-pago-card']",
);

mercadoPagoElements.forEach((element) => {
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

const paypalElements = document.querySelectorAll<HTMLElement>(
  "[data-stag-herd-checkout='paypal']",
);

paypalElements.forEach((element) => {
  createRoot(element).render(
    <PayPalCheckout
      clientId={element.dataset.clientId ?? ""}
      amount={Number(element.dataset.amount ?? 0)}
      currency={element.dataset.currency ?? "MXN"}
      externalReference={element.dataset.externalReference ?? ""}
      payerEmail={element.dataset.payerEmail ?? "cliente@test.com"}
      description={element.dataset.description ?? "Pago desde PayPal Checkout"}
      createOrderUrl={element.dataset.createOrderUrl ?? ""}
      captureOrderUrl={element.dataset.captureOrderUrl ?? ""}
      csrfToken={element.dataset.csrfToken ?? ""}
    />,
  );
});

const stripeElements = document.querySelectorAll<HTMLElement>(
  "[data-stag-herd-checkout='stripe-card']",
);

stripeElements.forEach((element) => {
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
