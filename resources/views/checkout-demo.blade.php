<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Stag Herd Checkout Demo</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @viteReactRefresh
    @vite(['resources/js/stag-herd/checkout.tsx'])
</head>

<body>
    <main style="max-width: 720px; margin: 40px auto; font-family: sans-serif;">
        <h1>Checkout demo</h1>

        <p>
            Monto:
            <strong>${{ number_format($amount, 2) }} {{ $currency }}</strong>
        </p>

        <div id="stag-herd-checkout" data-public-key="{{ $mercadoPagoPublicKey }}" data-amount="{{ $amount }}"
            data-currency="{{ $currency }}" data-external-reference="{{ $externalReference }}"
            data-payer-email="{{ $payerEmail }}" data-process-url="{{ route('checkout.demo.pay') }}"
            data-csrf-token="{{ csrf_token() }}"></div>
    </main>
</body>

</html>
