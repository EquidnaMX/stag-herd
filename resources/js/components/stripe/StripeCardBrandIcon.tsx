import { SVGProps } from "react";

type Props = {
  brand?: string | null;
  width?: number;
  height?: number;
  title?: string;
};

function normalizeBrand(brand?: string | null): string {
  return String(brand ?? "")
    .trim()
    .toLowerCase()
    .replace(/[\s-]+/g, "_");
}

function GenericCardIcon(props: SVGProps<SVGSVGElement>) {
  return (
    <svg viewBox="0 0 48 32" role="img" aria-label="Tarjeta" {...props}>
      <rect
        x="1"
        y="1"
        width="46"
        height="30"
        rx="4"
        fill="currentColor"
        fillOpacity="0.08"
        stroke="currentColor"
        strokeWidth="2"
      />

      <rect
        x="1"
        y="8"
        width="46"
        height="6"
        fill="currentColor"
        fillOpacity="0.35"
      />

      <rect
        x="6"
        y="21"
        width="12"
        height="4"
        rx="1"
        fill="currentColor"
        fillOpacity="0.35"
      />
    </svg>
  );
}

function VisaIcon(props: SVGProps<SVGSVGElement>) {
  return (
    <svg viewBox="0 0 64 40" role="img" aria-label="Visa" {...props}>
      <rect width="64" height="40" rx="5" fill="#ffffff" stroke="#d0d5dd" />

      <text
        x="32"
        y="25"
        textAnchor="middle"
        fontFamily="Arial, sans-serif"
        fontSize="18"
        fontWeight="700"
        fontStyle="italic"
        fill="#1434cb"
      >
        VISA
      </text>
    </svg>
  );
}

function MastercardIcon(props: SVGProps<SVGSVGElement>) {
  return (
    <svg viewBox="0 0 64 40" role="img" aria-label="Mastercard" {...props}>
      <rect width="64" height="40" rx="5" fill="#ffffff" stroke="#d0d5dd" />

      <circle cx="27" cy="20" r="11" fill="#eb001b" />

      <circle cx="37" cy="20" r="11" fill="#f79e1b" />

      <path
        d="M32 11.9a11 11 0 0 1 0 16.2 11 11 0 0 1 0-16.2Z"
        fill="#ff5f00"
      />
    </svg>
  );
}

function AmericanExpressIcon(props: SVGProps<SVGSVGElement>) {
  return (
    <svg
      viewBox="0 0 64 40"
      role="img"
      aria-label="American Express"
      {...props}
    >
      <rect width="64" height="40" rx="5" fill="#2e77bc" />

      <text
        x="32"
        y="17"
        textAnchor="middle"
        fontFamily="Arial, sans-serif"
        fontSize="9"
        fontWeight="700"
        fill="#ffffff"
      >
        AMERICAN
      </text>

      <text
        x="32"
        y="28"
        textAnchor="middle"
        fontFamily="Arial, sans-serif"
        fontSize="9"
        fontWeight="700"
        fill="#ffffff"
      >
        EXPRESS
      </text>
    </svg>
  );
}

function DiscoverIcon(props: SVGProps<SVGSVGElement>) {
  return (
    <svg viewBox="0 0 64 40" role="img" aria-label="Discover" {...props}>
      <rect width="64" height="40" rx="5" fill="#ffffff" stroke="#d0d5dd" />

      <text
        x="32"
        y="24"
        textAnchor="middle"
        fontFamily="Arial, sans-serif"
        fontSize="11"
        fontWeight="700"
        fill="#1f2937"
      >
        DISCOVER
      </text>

      <circle cx="37" cy="20" r="5" fill="#f58220" fillOpacity="0.85" />
    </svg>
  );
}

function JcbIcon(props: SVGProps<SVGSVGElement>) {
  return (
    <svg viewBox="0 0 64 40" role="img" aria-label="JCB" {...props}>
      <rect width="64" height="40" rx="5" fill="#ffffff" stroke="#d0d5dd" />

      <rect x="13" y="8" width="13" height="24" rx="3" fill="#0b4ea2" />

      <rect x="26" y="8" width="13" height="24" rx="3" fill="#dc1f2d" />

      <rect x="39" y="8" width="13" height="24" rx="3" fill="#159447" />

      <text
        x="32"
        y="24"
        textAnchor="middle"
        fontFamily="Arial, sans-serif"
        fontSize="10"
        fontWeight="700"
        fill="#ffffff"
      >
        JCB
      </text>
    </svg>
  );
}

function UnionPayIcon(props: SVGProps<SVGSVGElement>) {
  return (
    <svg viewBox="0 0 64 40" role="img" aria-label="UnionPay" {...props}>
      <rect width="64" height="40" rx="5" fill="#ffffff" stroke="#d0d5dd" />

      <rect x="13" y="8" width="15" height="24" rx="3" fill="#d71920" />

      <rect x="25" y="8" width="15" height="24" rx="3" fill="#005aa9" />

      <rect x="37" y="8" width="15" height="24" rx="3" fill="#009b72" />

      <text
        x="32"
        y="23"
        textAnchor="middle"
        fontFamily="Arial, sans-serif"
        fontSize="7"
        fontWeight="700"
        fill="#ffffff"
      >
        UnionPay
      </text>
    </svg>
  );
}

export function StripeBrandIcon({
  brand,
  width = 48,
  height = 30,
  title,
}: Props) {
  const normalizedBrand = normalizeBrand(brand);

  const commonProps: SVGProps<SVGSVGElement> = {
    width,
    height,
    focusable: false,
    "aria-label": title || brand || "Tarjeta",
  };

  switch (normalizedBrand) {
    case "visa":
      return <VisaIcon {...commonProps} />;

    case "mastercard":
    case "master_card":
      return <MastercardIcon {...commonProps} />;

    case "amex":
    case "american_express":
      return <AmericanExpressIcon {...commonProps} />;

    case "discover":
      return <DiscoverIcon {...commonProps} />;

    case "jcb":
      return <JcbIcon {...commonProps} />;

    case "unionpay":
    case "union_pay":
      return <UnionPayIcon {...commonProps} />;

    default:
      return <GenericCardIcon {...commonProps} />;
  }
}
