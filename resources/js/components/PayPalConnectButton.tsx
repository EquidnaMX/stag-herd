import { CSSProperties, useState } from "react";

type Status = "idle" | "loading" | "redirecting" | "error";

type MetadataValue = string | number | boolean | null;
type ExtraPayload = Record<string, MetadataValue | MetadataValue[] | Record<string, unknown>>;

type Props = {
    onboardingUrl: string;
    returnUrl: string;

    csrfToken?: string;
    trackingId?: string;
    actionRenewalUrl?: string;
    credentialContext?: string;
    platformAttributionId?: string;

    label?: string;
    helperText?: string;
    disabled?: boolean;

    className?: string;
    style?: CSSProperties;
    buttonStyle?: CSSProperties;

    extraPayload?: ExtraPayload;
    extraHeaders?: Record<string, string>;

    onStatusChange?: (status: Status, message?: string) => void;
    onBeforeRedirect?: (actionUrl: string, response: unknown) => void | Promise<void>;
    onError?: (error: unknown) => void;
};

async function parseJsonResponse(response: Response): Promise<any> {
    const text = await response.text();

    try {
        return text ? JSON.parse(text) : null;
    } catch {
        throw new Error(
            `The backend did not return JSON. Status ${response.status}. Response: ${text.substring(0, 300)}`,
        );
    }
}

function getBackendErrorMessage(data: any, fallback: string): string {
    return (
        data?.message ||
        data?.errors?.[0] ||
        data?.error ||
        data?.paypal_response?.message ||
        fallback
    );
}

export function PayPalConnectButton({
    onboardingUrl,
    returnUrl,
    csrfToken,
    trackingId,
    actionRenewalUrl,
    credentialContext,
    platformAttributionId,
    label = "Connect with PayPal",
    helperText = "Connect your PayPal account to start receiving payments.",
    disabled = false,
    className,
    style,
    buttonStyle,
    extraPayload,
    extraHeaders,
    onStatusChange,
    onBeforeRedirect,
    onError,
}: Props) {
    const [status, setStatus] = useState<Status>("idle");
    const [message, setMessage] = useState("");

    function updateStatus(nextStatus: Status, nextMessage = "") {
        setStatus(nextStatus);
        setMessage(nextMessage);
        onStatusChange?.(nextStatus, nextMessage);
    }

    async function handleClick() {
        try {
            updateStatus("loading");

            if (!onboardingUrl) {
                throw new Error("Missing onboardingUrl.");
            }

            if (!returnUrl) {
                throw new Error("Missing returnUrl.");
            }

            const response = await fetch(onboardingUrl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                    ...(csrfToken ? { "X-CSRF-TOKEN": csrfToken } : {}),
                    ...extraHeaders,
                },
                credentials: "include",
                body: JSON.stringify({
                    tracking_id: trackingId,
                    return_url: returnUrl,
                    action_renewal_url: actionRenewalUrl,
                    credential_context: credentialContext,
                    platform_attribution_id: platformAttributionId,
                    ...extraPayload,
                }),
            });

            const data = await parseJsonResponse(response);

            if (!response.ok || !data?.ok) {
                throw new Error(
                    getBackendErrorMessage(
                        data,
                        `Could not create PayPal onboarding link. Status ${response.status}`,
                    ),
                );
            }

            const actionUrl = data?.action_url;

            if (!actionUrl) {
                throw new Error("The backend did not return action_url.");
            }

            updateStatus("redirecting", "Redirecting to PayPal...");

            await onBeforeRedirect?.(actionUrl, data);

            window.location.href = actionUrl;
        } catch (error) {
            console.error(error);

            const errorMessage =
                error instanceof Error && error.message
                    ? error.message
                    : "Could not start PayPal onboarding.";

            updateStatus("error", errorMessage);
            onError?.(error);
        }
    }

    const isBusy = status === "loading" || status === "redirecting";

    return (
        <div
            className={className}
            style={{
                width: "100%",
                maxWidth: 420,
                borderRadius: 18,
                padding: 18,
                border: "1px solid #e5e7eb",
                background:
                    "linear-gradient(135deg, #ffffff 0%, #f8fafc 55%, #eff6ff 100%)",
                boxShadow: "0 12px 32px rgba(15, 23, 42, 0.10)",
                ...style,
            }}
        >
            <div style={{ display: "flex", gap: 14, alignItems: "center" }}>
                <div
                    aria-hidden="true"
                    style={{
                        width: 48,
                        height: 48,
                        borderRadius: 16,
                        display: "grid",
                        placeItems: "center",
                        background: "#003087",
                        color: "#ffffff",
                        fontWeight: 800,
                        fontSize: 20,
                        boxShadow: "0 8px 20px rgba(0, 48, 135, 0.25)",
                    }}
                >
                    P
                </div>

                <div style={{ minWidth: 0 }}>
                    <div
                        style={{
                            color: "#0f172a",
                            fontSize: 16,
                            fontWeight: 750,
                            lineHeight: 1.2,
                        }}
                    >
                        PayPal
                    </div>

                    <div
                        style={{
                            marginTop: 4,
                            color: "#475569",
                            fontSize: 13,
                            lineHeight: 1.35,
                        }}
                    >
                        {helperText}
                    </div>
                </div>
            </div>

            {status === "error" && message && (
                <div
                    role="alert"
                    style={{
                        marginTop: 14,
                        padding: "10px 12px",
                        borderRadius: 12,
                        border: "1px solid #fecaca",
                        background: "#fef2f2",
                        color: "#991b1b",
                        fontSize: 13,
                        lineHeight: 1.4,
                    }}
                >
                    {message}
                </div>
            )}

            {status === "redirecting" && message && (
                <div
                    style={{
                        marginTop: 14,
                        color: "#2563eb",
                        fontSize: 13,
                        fontWeight: 600,
                    }}
                >
                    {message}
                </div>
            )}

            <button
                type="button"
                disabled={disabled || isBusy}
                onClick={handleClick}
                style={{
                    marginTop: 16,
                    width: "100%",
                    border: 0,
                    borderRadius: 999,
                    padding: "12px 18px",
                    cursor: disabled || isBusy ? "not-allowed" : "pointer",
                    background: disabled || isBusy ? "#cbd5e1" : "#ffc439",
                    color: "#111827",
                    fontSize: 15,
                    fontWeight: 800,
                    letterSpacing: 0.1,
                    boxShadow:
                        disabled || isBusy
                            ? "none"
                            : "0 10px 22px rgba(255, 196, 57, 0.35)",
                    transition: "transform 150ms ease, box-shadow 150ms ease",
                    ...buttonStyle,
                }}
            >
                {isBusy ? "Connecting..." : label}
            </button>
        </div>
    );
}