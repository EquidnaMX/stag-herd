import { createRoot } from "react-dom/client";
import { MercadoPagoCardCheckout } from "./components/MercadoPagoCardCheckout";

const element = document.getElementById("stag-herd-checkout");

if (!element) {
  throw new Error("No se encontró el contenedor #stag-herd-checkout");
}

createRoot(element).render(
  <MercadoPagoCardCheckout
    publicKey={element.dataset.publicKey ?? ""}
    amount={Number(element.dataset.amount ?? 0)}
    currency={element.dataset.currency ?? "MXN"}
    externalReference={element.dataset.externalReference ?? ""}
    payerEmail={element.dataset.payerEmail ?? ""}
    processUrl={element.dataset.processUrl ?? ""}
    csrfToken={element.dataset.csrfToken ?? ""}
  />,
);
