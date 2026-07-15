import { StripeBrandIcon } from "./StripeBrandIcon";

export type StripeSavedPaymentMethod = {
  /**
   * Identificador propio del host.
   */
  id: string | number;

  /**
   * Identificadores de Stripe.
   */
  customerId: string;
  paymentMethodId: string;

  brand?: string | null;
  lastFour?: string | null;

  expMonth?: number | null;
  expYear?: number | null;

  cardholderName?: string | null;

  /**
   * El host puede identificar una tarjeta predeterminada.
   */
  isDefault?: boolean;
};

type Props = {
  paymentMethod: StripeSavedPaymentMethod;
  selected: boolean;
  disabled?: boolean;
  name?: string;

  onSelect: (paymentMethod: StripeSavedPaymentMethod) => void;
};

function formatBrand(brand?: string | null): string {
  if (!brand) {
    return "Tarjeta";
  }

  const normalized = brand.replace(/[_-]/g, " ").trim();

  return normalized
    .split(/\s+/)
    .map((word) => {
      return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
    })
    .join(" ");
}

function formatExpiration(
  month?: number | null,
  year?: number | null,
): string | null {
  if (!month || !year) {
    return null;
  }

  return `${String(month).padStart(2, "0")}/${String(year).slice(-2)}`;
}

export function StripeSavedCardOption({
  paymentMethod,
  selected,
  disabled = false,
  name = "stripe_saved_payment_method",
  onSelect,
}: Props) {
  const inputId = `stripe-saved-card-${paymentMethod.id}`;

  const expiration = formatExpiration(
    paymentMethod.expMonth,
    paymentMethod.expYear,
  );

  return (
    <label htmlFor={inputId}>
      <input
        id={inputId}
        type="radio"
        name={name}
        value={String(paymentMethod.id)}
        checked={selected}
        disabled={disabled}
        onChange={() => {
          onSelect(paymentMethod);
        }}
      />

      <StripeBrandIcon
        brand={paymentMethod.brand}
        title={formatBrand(paymentMethod.brand)}
      />

      <span>
        <strong>{formatBrand(paymentMethod.brand)}</strong>

        {paymentMethod.lastFour ? ` •••• ${paymentMethod.lastFour}` : ""}
      </span>

      {expiration && <span> — {expiration}</span>}

      {paymentMethod.isDefault && <span> — Predeterminada</span>}
    </label>
  );
}
