import React from "react";
import { createRoot } from "react-dom/client";
import { MercadoPagoCardCheckout } from "./components/MercadoPagoCardCheckout";

const elements = document.querySelectorAll<HTMLElement>(
  "[data-stag-herd-checkout='mercado-pago-card']",
);

elements.forEach((element) => {
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
