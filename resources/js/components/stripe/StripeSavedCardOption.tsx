import type { MouseEvent } from "react";
import { useEffect, useRef, useState } from "react";

import { StripeBrandIcon } from "./StripeCardBrandIcon";

export type StripeSavedPaymentMethod = {
  id: string | number;

  customerId: string;
  paymentMethodId: string;

  brand?: string | null;
  displayName?: string | null;
  display_name?: string | null;
  lastFour?: string | null;

  expMonth?: number | null;
  expYear?: number | null;

  cardholderName?: string | null;

  isDefault?: boolean;
};

type Props = {
  paymentMethod: StripeSavedPaymentMethod;
  selected: boolean;
  disabled?: boolean;
  name?: string;

  onSelect: (paymentMethod: StripeSavedPaymentMethod) => void;

  onDelete?: (paymentMethod: StripeSavedPaymentMethod) => void | Promise<void>;

  onSetDefault?: (
    paymentMethod: StripeSavedPaymentMethod,
  ) => void | Promise<void>;
};

function formatBrand(brand?: string | null): string {
  if (!brand) {
    return "Tarjeta";
  }

  return brand
    .replace(/[_-]/g, " ")
    .trim()
    .split(/\s+/)
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
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

function resolveDisplayName(
  paymentMethod: StripeSavedPaymentMethod,
): string | null {
  const displayName = String(
    paymentMethod.displayName ?? paymentMethod.display_name ?? "",
  ).trim();

  return displayName || null;
}

export function StripeSavedCardOption({
  paymentMethod,
  selected,
  disabled = false,
  name = "stripe_saved_payment_method",
  onSelect,
  onDelete,
  onSetDefault,
}: Props) {
  const [menuOpen, setMenuOpen] = useState(false);
  const [actionPending, setActionPending] = useState(false);

  const menuRef = useRef<HTMLDivElement>(null);

  const inputId = `stripe-saved-card-${paymentMethod.id}`;

  const expiration = formatExpiration(
    paymentMethod.expMonth,
    paymentMethod.expYear,
  );

  const brandName = formatBrand(paymentMethod.brand);
  const displayName = resolveDisplayName(paymentMethod);

  const hasActions =
    Boolean(onDelete) || (Boolean(onSetDefault) && !paymentMethod.isDefault);

  useEffect(() => {
    function handleOutsideClick(event: globalThis.MouseEvent): void {
      if (menuRef.current && !menuRef.current.contains(event.target as Node)) {
        setMenuOpen(false);
      }
    }

    function handleEscape(event: KeyboardEvent): void {
      if (event.key === "Escape") {
        setMenuOpen(false);
      }
    }

    document.addEventListener("mousedown", handleOutsideClick);
    document.addEventListener("keydown", handleEscape);

    return () => {
      document.removeEventListener("mousedown", handleOutsideClick);
      document.removeEventListener("keydown", handleEscape);
    };
  }, []);

  async function handleMenuAction(
    event: MouseEvent<HTMLButtonElement>,
    action: () => void | Promise<void>,
  ): Promise<void> {
    event.preventDefault();
    event.stopPropagation();

    if (disabled || actionPending) {
      return;
    }

    setActionPending(true);

    try {
      await action();
      setMenuOpen(false);
    } finally {
      setActionPending(false);
    }
  }

  return (
    <div
      className={[
        "group relative flex w-full items-center rounded-2xl border",
        "transition-all duration-200",
        selected
          ? "border-rose-400 bg-rose-50/50 shadow-sm ring-1 ring-rose-400"
          : "border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50/70",
        disabled ? "opacity-60" : "",
      ].join(" ")}
    >
      <label
        htmlFor={inputId}
        className={[
          "flex min-w-0 flex-1 items-center gap-4 p-4",
          disabled ? "cursor-not-allowed" : "cursor-pointer",
        ].join(" ")}
      >
        <input
          id={inputId}
          type="radio"
          name={name}
          value={String(paymentMethod.id)}
          checked={selected}
          disabled={disabled}
          onChange={() => onSelect(paymentMethod)}
          className="sr-only"
        />

        <span
          aria-hidden="true"
          className={[
            "flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2",
            "transition-colors duration-200",
            selected
              ? "border-rose-500"
              : "border-slate-300 group-hover:border-slate-400",
          ].join(" ")}
        >
          {selected && (
            <span className="h-2.5 w-2.5 rounded-full bg-rose-500" />
          )}
        </span>

        <div className="flex h-12 w-[68px] shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white shadow-sm">
          <StripeBrandIcon
            brand={paymentMethod.brand}
            title={brandName}
            width={52}
            height={32}
          />
        </div>

        <div className="min-w-0 flex-1">
          <div className="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1">
            <span className="truncate text-sm font-semibold text-slate-900">
              {displayName ?? brandName}
            </span>

            {paymentMethod.lastFour && (
              <span className="shrink-0 text-sm font-medium text-slate-600">
                •••• {paymentMethod.lastFour}
              </span>
            )}

            {paymentMethod.isDefault && (
              <span className="shrink-0 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold tracking-wide text-emerald-700 uppercase">
                Predeterminada
              </span>
            )}
          </div>

          <div className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-500">
            {displayName && <span>{brandName}</span>}

            {displayName && expiration && (
              <span aria-hidden="true" className="text-slate-300">
                •
              </span>
            )}

            {expiration && <span>Vence {expiration}</span>}

            {paymentMethod.cardholderName && (
              <>
                {(displayName || expiration) && (
                  <span aria-hidden="true" className="text-slate-300">
                    •
                  </span>
                )}

                <span className="max-w-40 truncate">
                  {paymentMethod.cardholderName}
                </span>
              </>
            )}
          </div>
        </div>
      </label>

      {hasActions && (
        <div ref={menuRef} className="relative mr-3 shrink-0 self-center">
          <button
            type="button"
            aria-label="Mostrar opciones de la tarjeta"
            aria-haspopup="menu"
            aria-expanded={menuOpen}
            disabled={disabled || actionPending}
            onClick={(event) => {
              event.preventDefault();
              event.stopPropagation();

              setMenuOpen((current) => !current);
            }}
            className={[
              "flex h-9 w-9 items-center justify-center rounded-lg",
              "text-slate-500 transition-colors",
              "hover:bg-white hover:text-slate-800 hover:shadow-sm",
              "focus-visible:ring-2 focus-visible:ring-rose-400 focus-visible:outline-none",
              "disabled:cursor-not-allowed disabled:opacity-60",
              menuOpen ? "bg-white text-slate-800 shadow-sm" : "",
            ].join(" ")}
          >
            {actionPending ? (
              <svg
                aria-hidden="true"
                viewBox="0 0 24 24"
                fill="none"
                className="h-5 w-5 animate-spin"
              >
                <circle
                  cx="12"
                  cy="12"
                  r="9"
                  stroke="currentColor"
                  strokeWidth="2"
                  className="opacity-25"
                />

                <path
                  d="M21 12a9 9 0 0 0-9-9"
                  stroke="currentColor"
                  strokeWidth="2"
                  strokeLinecap="round"
                />
              </svg>
            ) : (
              <svg
                aria-hidden="true"
                viewBox="0 0 24 24"
                fill="currentColor"
                className="h-5 w-5"
              >
                <circle cx="5" cy="12" r="1.75" />
                <circle cx="12" cy="12" r="1.75" />
                <circle cx="19" cy="12" r="1.75" />
              </svg>
            )}
          </button>

          {menuOpen && (
            <div
              role="menu"
              className={[
                "absolute top-11 right-0 z-30 w-56 overflow-hidden",
                "rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg",
              ].join(" ")}
            >
              {!paymentMethod.isDefault && onSetDefault && (
                <button
                  type="button"
                  role="menuitem"
                  disabled={actionPending}
                  onClick={(event) =>
                    void handleMenuAction(event, async () => {
                      await onSetDefault(paymentMethod);

                      onSelect(paymentMethod);
                    })
                  }
                  className={[
                    "flex w-full items-center gap-3 rounded-lg px-3 py-2.5",
                    "text-left text-sm font-medium text-slate-700",
                    "transition-colors hover:bg-slate-100 hover:text-slate-900",
                    "disabled:cursor-wait disabled:opacity-60",
                  ].join(" ")}
                >
                  <svg
                    aria-hidden="true"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="1.8"
                    className="h-5 w-5 shrink-0"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      d="m12 3 2.7 5.47 6.03.88-4.36 4.25 1.03 6-5.4-2.84-5.4 2.84 1.03-6-4.36-4.25 6.03-.88L12 3Z"
                    />
                  </svg>
                  Marcar como predeterminada
                </button>
              )}

              {onDelete && (
                <>
                  {!paymentMethod.isDefault && onSetDefault && (
                    <div className="my-1 border-t border-slate-100" />
                  )}

                  <button
                    type="button"
                    role="menuitem"
                    disabled={actionPending}
                    onClick={(event) =>
                      void handleMenuAction(event, () =>
                        onDelete(paymentMethod),
                      )
                    }
                    className={[
                      "flex w-full items-center gap-3 rounded-lg px-3 py-2.5",
                      "text-left text-sm font-medium text-red-600",
                      "transition-colors hover:bg-red-50 hover:text-red-700",
                      "disabled:cursor-wait disabled:opacity-60",
                    ].join(" ")}
                  >
                    <svg
                      aria-hidden="true"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      strokeWidth="1.8"
                      className="h-5 w-5 shrink-0"
                    >
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        d="M4 7h16M10 11v6m4-6v6M9 7l1-3h4l1 3m3 0-1 13H7L6 7"
                      />
                    </svg>
                    Eliminar tarjeta
                  </button>
                </>
              )}
            </div>
          )}
        </div>
      )}
    </div>
  );
}
