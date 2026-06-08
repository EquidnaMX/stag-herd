<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Stag Herd Payments Demo</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @viteReactRefresh
    @vite(['resources/js/checkout.tsx'])

    <style>
        body {
            margin: 0;
            background: #f6f7fb;
            color: #172033;
            font-family: Arial, Helvetica, sans-serif;
        }

        main {
            max-width: 1500px;
            margin: 32px auto;
            padding: 0 18px;
        }

        h1 {
            margin: 0 0 6px;
        }

        h2 {
            margin-top: 0;
            font-size: 18px;
        }

        .muted {
            color: #64748b;
        }

        .grid {
            display: grid;
            grid-template-columns: 420px minmax(0, 1fr);
            gap: 18px;
            align-items: start;
        }

        .grid>aside,
        .grid>section,
        .card {
            min-width: 0;
        }

        .card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 18px;
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin: 12px 0 6px;
            font-weight: bold;
            font-size: 13px;
        }

        input,
        select {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 10px 12px;
            font: inherit;
            background: #ffffff;
        }

        button,
        .button {
            display: inline-block;
            border: 0;
            border-radius: 8px;
            background: #111827;
            color: #ffffff;
            padding: 9px 12px;
            font-weight: bold;
            text-decoration: none;
            cursor: pointer;
            font: inherit;
            text-align: center;
            box-sizing: border-box;
        }

        button.secondary,
        .button.secondary {
            background: #334155;
        }

        button.success,
        .button.success {
            background: #047857;
        }

        button.warning,
        .button.warning {
            background: #b45309;
        }

        button.danger,
        .button.danger {
            background: #b91c1c;
        }

        button.provider,
        .button.provider {
            background: #2563eb;
        }

        .actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 160px;
        }

        .actions form {
            margin: 0;
        }

        .actions button,
        .actions .button {
            width: 100%;
            font-size: 13px;
            padding: 8px 10px;
        }

        .two {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .hidden {
            display: none;
        }

        .mt {
            margin-top: 18px;
        }

        .mb {
            margin-bottom: 18px;
        }

        .alert {
            border-radius: 12px;
            padding: 12px 14px;
            margin: 16px 0;
        }

        .alert.ok {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
        }

        .alert.error {
            background: #fef2f2;
            border: 1px solid #fecaca;
        }

        pre {
            overflow: auto;
            background: #0f172a;
            color: #e5e7eb;
            padding: 14px;
            border-radius: 12px;
            font-size: 12px;
            max-width: 100%;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 900px;
            border-collapse: collapse;
            font-size: 14px;
            table-layout: auto;
        }

        th,
        td {
            padding: 10px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            vertical-align: top;
        }

        th {
            color: #475569;
            font-size: 12px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        td {
            overflow-wrap: normal;
            word-break: normal;
        }

        th:nth-child(1),
        td:nth-child(1) {
            width: 80px;
            white-space: nowrap;
        }

        th:nth-child(2),
        td:nth-child(2) {
            width: 150px;
        }

        th:nth-child(3),
        td:nth-child(3) {
            width: 110px;
            white-space: nowrap;
        }

        th:nth-child(4),
        td:nth-child(4) {
            width: 160px;
        }

        th:nth-child(5),
        td:nth-child(5) {
            min-width: 240px;
            max-width: 340px;
            overflow-wrap: anywhere;
        }

        th:nth-child(6),
        td:nth-child(6) {
            width: 180px;
        }

        .pill {
            display: inline-block;
            border-radius: 999px;
            padding: 4px 9px;
            background: #e2e8f0;
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
        }

        .pill.APPROVED,
        .pill.REFUNDED {
            background: #dcfce7;
            color: #166534;
        }

        .pill.PENDING,
        .pill.PROCESSING {
            background: #fef3c7;
            color: #92400e;
        }

        .pill.REJECTED,
        .pill.FAILED,
        .pill.CANCELED,
        .pill.CANCELLED {
            background: #fee2e2;
            color: #991b1b;
        }

        .search {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 10px;
            margin-bottom: 14px;
        }

        .search button {
            white-space: nowrap;
        }

        .note {
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 12px;
            padding: 12px;
        }

        small {
            display: block;
            margin-top: 6px;
        }

        code {
            background: #f1f5f9;
            border-radius: 6px;
            padding: 2px 5px;
        }

        @media (max-width: 1100px) {
            main {
                max-width: 100%;
            }

            .grid {
                grid-template-columns: 1fr;
            }

            table {
                min-width: 900px;
            }
        }

        @media (max-width: 900px) {

            .two,
            .search {
                grid-template-columns: 1fr;
            }

            .actions {
                min-width: 150px;
            }
        }
    </style>
</head>

<body>
    <main>
        <header class="mb">
            <h1>Stag Herd Payments Demo</h1>

            <p class="muted">
                Panel simple para probar creación, lookup directo al provider, sync provider, cancelación, reembolso y
                pagos locales.
            </p>
        </header>

        @if ($result ?? null)
            <section class="alert ok">
                <strong>Acción ejecutada:</strong> {{ $result['action'] ?? 'unknown' }}

                @if (!empty($result['checkout_url']))
                    <p>Mercado Pago regresó una URL de checkout:</p>

                    <a class="button provider" href="{{ $result['checkout_url'] }}" target="_blank" rel="noopener">
                        Abrir checkout
                    </a>
                @endif

                <pre>{{ json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            </section>
        @endif

        @if ($providerResult ?? null)
            <section class="alert ok">
                <strong>Resultado directo del provider</strong>

                <p>
                    <strong>Acción:</strong> {{ $providerResult['action'] ?? 'provider_lookup' }}
                    <br>
                    <strong>Provider:</strong> {{ $providerResult['provider'] ?? '—' }}
                    <br>
                    <strong>Tipo:</strong> {{ $providerResult['search_type'] ?? '—' }}
                    <br>
                    <strong>Valor:</strong> {{ $providerResult['search_value'] ?? '—' }}
                </p>

                <pre>{{ json_encode($providerResult['response'] ?? $providerResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            </section>
        @endif

        @if ($error ?? null)
            <section class="alert error">
                <strong>{{ $error['type'] ?? 'Error' }}</strong>
                <p>{{ $error['message'] ?? 'Ocurrió un error.' }}</p>

                @if (!empty($error['context']))
                    <pre>{{ json_encode($error['context'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                @endif
            </section>
        @endif

        <section class="grid">
            <aside>
                <section class="card">
                    <h2>Crear pago de prueba</h2>

                    <p class="muted">
                        Para <strong>cash</strong> usa el formulario simple.
                        Para <strong>Mercado Pago card</strong>, selecciona provider mercado_pago y usa el Brick de
                        abajo.
                    </p>

                    <form method="POST" action="{{ route('stag-herd.payments.store') }}">
                        @csrf

                        <div class="two">
                            <div>
                                <label for="provider">Provider</label>
                                <select id="provider" name="provider">
                                    <option value="cash">cash</option>
                                    <option value="mercado_pago">mercado_pago</option>
                                </select>
                            </div>

                            <div>
                                <label for="method">Método</label>
                                <select id="method" name="method"></select>
                            </div>
                        </div>

                        <div class="two">
                            <div>
                                <label for="amount">Monto</label>
                                <input id="amount" name="amount" type="number" step="0.01" min="0.01"
                                    value="{{ old('amount', '120.00') }}">
                            </div>

                            <div>
                                <label for="currency">Moneda</label>
                                <input id="currency" name="currency" maxlength="3"
                                    value="{{ old('currency', 'MXN') }}">
                            </div>
                        </div>

                        <div class="two">
                            <div>
                                <label for="metadata_id_order">Metadata: id_order</label>
                                <input id="metadata_id_order" name="metadata[id_order]" type="number"
                                    data-stag-herd-metadata="id_order" value="{{ old('metadata.id_order') }}"
                                    placeholder="Ej. 589 si usas Fresh">

                                <small class="muted">
                                    Aquí va la orden real de Fresh. El paquete no la trata como columna propia; solo
                                    viaja en metadata.
                                </small>
                            </div>

                            <div>
                                <label for="metadata_id_client">Metadata: id_client</label>
                                <input id="metadata_id_client" name="metadata[id_client]"
                                    data-stag-herd-metadata="id_client"
                                    value="{{ old('metadata.id_client', 'CLIENT-DEMO') }}"
                                    placeholder="Ej. cliente Fresh">

                                <small class="muted">
                                    Campo genérico dentro de metadata. Fresh lo puede usar para Payments.id_client.
                                </small>
                            </div>
                        </div>

                        <label for="external_reference">External reference</label>
                        <input type="text" name="external_reference" id="external_reference"
                            value="{{ old('external_reference') }}"
                            placeholder="Ej. ORDER-589, CHECKOUT-ABC o referencia del host">

                        <small class="muted">
                            Referencia de negocio. No es la orden obligatoria de Fresh; la orden de Fresh va en
                            metadata[id_order].
                        </small>

                        <div class="two">
                            <div>
                                <label for="payer_reference">Payer reference</label>
                                <input id="payer_reference" name="payer_reference"
                                    value="{{ old('payer_reference', 'CLIENT-DEMO') }}">
                            </div>

                            <div>
                                <label for="payer_email">Payer email</label>
                                <input id="payer_email" name="payer_email" type="email"
                                    value="{{ old('payer_email', 'cliente@test.com') }}">
                            </div>
                        </div>

                        <label for="description">Descripción</label>
                        <input id="description" name="description"
                            value="{{ old('description', 'Pago de prueba desde UI del paquete') }}">

                        <div class="cash-fields">
                            <label for="cash_status">Cash status</label>
                            <select id="cash_status" name="cash_status">
                                <option value="approved">approved</option>
                                <option value="pending">pending</option>
                                <option value="rejected">rejected</option>
                                <option value="failed">failed</option>
                            </select>
                        </div>

                        <div class="mercado-pago-fields hidden">
                            <div class="note mt">
                                Para Mercado Pago con tarjeta, usa el Brick. Este formulario manual solo sirve si ya
                                tienes un token generado.
                            </div>

                            <label for="mercado_pago_token">Token de tarjeta</label>
                            <input id="mercado_pago_token" name="mercado_pago_token"
                                placeholder="token generado por Mercado Pago">

                            <label for="mercado_pago_payment_method_id">Payment method id</label>
                            <input id="mercado_pago_payment_method_id" name="mercado_pago_payment_method_id"
                                placeholder="visa, master, account_money">

                            <div class="two">
                                <div>
                                    <label for="mercado_pago_issuer_id">Issuer id</label>
                                    <input id="mercado_pago_issuer_id" name="mercado_pago_issuer_id">
                                </div>

                                <div>
                                    <label for="mercado_pago_installments">Installments</label>
                                    <input id="mercado_pago_installments" name="mercado_pago_installments"
                                        type="number" min="1" value="1">
                                </div>
                            </div>
                        </div>

                        <button class="mt" type="submit">Crear pago</button>
                    </form>
                </section>

                <section class="card mercado-pago-fields hidden">
                    <h2>Checkout Brick Mercado Pago</h2>

                    <p class="muted">
                        Este bloque renderiza el Card Payment Brick y manda el token al backend del paquete.
                        Si usas Fresh, escribe primero la orden en <strong>Metadata: id_order</strong> arriba.
                    </p>

                    <div class="note mb">
                        <strong>¿Dónde meto la orden?</strong>
                        <br>
                        En el campo de arriba llamado <strong>Metadata: id_order</strong>.
                        El Brick lee todos los inputs que tengan <code>data-stag-herd-metadata</code> y los manda dentro
                        de
                        <code>metadata</code>.
                    </div>

                    <div data-stag-herd-checkout="mercado-pago-card"
                        data-public-key="{{ config('stag-herd.providers.mercado_pago.credentials.public_key') }}"
                        data-amount="120.00" data-currency="MXN" data-external-reference=""
                        data-payer-email="cliente@test.com" data-description="Pago desde Mercado Pago Card Brick"
                        data-process-url="{{ route('stag-herd.payments.brick.process') }}"
                        data-csrf-token="{{ csrf_token() }}"></div>
                </section>

                <section class="card">
                    <h2>Lookup directo en provider</h2>

                    <p class="muted">
                        Consulta Mercado Pago directamente. No guarda ni actualiza tu base local.
                    </p>

                    <form method="POST" action="{{ route('stag-herd.payments.provider.lookup') }}">
                        @csrf

                        <input type="hidden" name="provider" value="mercado_pago">

                        <label for="provider_lookup_type">Buscar por</label>
                        <select id="provider_lookup_type" name="search_type">
                            <option value="provider_payment_id">provider_payment_id</option>
                            <option value="provider_order_id">provider_order_id</option>
                        </select>

                        <label for="provider_lookup_value">Valor</label>
                        <input id="provider_lookup_value" name="search_value"
                            placeholder="Ej. payment id u order id visible del provider" required>

                        <button class="provider mt" type="submit">
                            Lookup provider
                        </button>
                    </form>
                </section>

                <section class="card">
                    <h2>Sync desde provider</h2>

                    <p class="muted">
                        Consulta Mercado Pago. Si el pago existe localmente, lo actualiza. Si no existe, lo crea.
                    </p>

                    <form method="POST" action="{{ route('stag-herd.payments.provider.sync') }}">
                        @csrf

                        <input type="hidden" name="provider" value="mercado_pago">

                        <label for="sync_search_type">Buscar en provider por</label>
                        <select id="sync_search_type" name="search_type">
                            <option value="provider_payment_id">provider_payment_id</option>
                            <option value="provider_order_id">provider_order_id</option>
                        </select>

                        <label for="sync_search_value">Valor</label>
                        <input id="sync_search_value" name="search_value"
                            placeholder="Ej. payment id u order id visible del provider" required>

                        <div class="two">
                            <div>
                                <label for="sync_method">Método local</label>
                                <select id="sync_method" name="method">
                                    <option value="card">card</option>
                                    <option value="wallet">wallet</option>
                                </select>
                            </div>

                            <div>
                                <label for="sync_currency">Moneda</label>
                                <input id="sync_currency" name="currency" value="MXN" maxlength="3">
                            </div>
                        </div>

                        <label for="sync_amount">Monto fallback</label>
                        <input id="sync_amount" name="amount" type="number" step="0.01" min="0.01"
                            value="120.00">

                        <div class="two">
                            <div>
                                <label for="sync_metadata_id_order">Metadata fallback: id_order</label>
                                <input id="sync_metadata_id_order" name="metadata[id_order]" type="number"
                                    placeholder="Ej. 589 si usas Fresh">

                                <small class="muted">
                                    Necesario si el sync tiene que crear un pago local en Fresh.
                                </small>
                            </div>

                            <div>
                                <label for="sync_metadata_id_client">Metadata fallback: id_client</label>
                                <input id="sync_metadata_id_client" name="metadata[id_client]"
                                    placeholder="Ej. cliente Fresh">
                            </div>
                        </div>

                        <label for="sync_external_reference">External reference fallback</label>
                        <input id="sync_external_reference" name="external_reference" placeholder="Ej. ORDER-589">

                        <div class="two">
                            <div>
                                <label for="sync_payer_reference">Payer reference</label>
                                <input id="sync_payer_reference" name="payer_reference" value="CLIENT-DEMO">
                            </div>

                            <div>
                                <label for="sync_payer_email">Payer email</label>
                                <input id="sync_payer_email" name="payer_email" type="email"
                                    value="cliente@test.com">
                            </div>
                        </div>

                        <label for="sync_description">Descripción</label>
                        <input id="sync_description" name="description" value="Pago sincronizado desde Mercado Pago">

                        <button class="success mt" type="submit">
                            Sync provider
                        </button>
                    </form>
                </section>
            </aside>

            <section>
                <section class="card">
                    <h2>Pagos locales</h2>

                    <p class="muted">
                        Esta tabla muestra tu persistencia local. El botón Sync/Actualizar consulta provider y actualiza
                        local si aplica.
                    </p>

                    <form class="search" method="GET" action="{{ route('stag-herd.payments.index') }}">
                        <input name="search" value="{{ $search ?? '' }}"
                            placeholder="Buscar local por id, metadata.id_order, provider_payment_id, provider_order_id...">

                        <button type="submit">Buscar local</button>
                    </form>

                    @if (($search ?? '') !== '')
                        <p class="muted">
                            Resultado local para: <strong>{{ $search }}</strong>
                            <a href="{{ route('stag-herd.payments.index') }}">Limpiar</a>
                        </p>
                    @endif

                    @if ($payments->isEmpty())
                        <p class="muted">No hay pagos locales para mostrar.</p>
                    @else
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID local</th>
                                        <th>Provider</th>
                                        <th>Monto</th>
                                        <th>Estado</th>
                                        <th>Referencias</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @foreach ($payments as $payment)
                                        @php
                                            $paymentId = data_get($payment, 'id');
                                            $provider = data_get($payment, 'provider', '—');
                                            $method = data_get($payment, 'method', '—');
                                            $amount = (int) data_get($payment, 'amount', 0);
                                            $currency = data_get($payment, 'currency', 'MXN');
                                            $status = data_get($payment, 'status', 'PENDING');
                                            $providerStatus = data_get($payment, 'provider_status');
                                            $metadata = data_get($payment, 'metadata', []);
                                            $hostOrderId =
                                                data_get($metadata, 'id_order') ?? data_get($payment, 'id_order');
                                            $externalReference = data_get($payment, 'external_reference');
                                            $providerPaymentId = data_get($payment, 'provider_payment_id');
                                            $providerOrderId = data_get($payment, 'provider_order_id');
                                        @endphp

                                        <tr>
                                            <td>#{{ $paymentId }}</td>

                                            <td>
                                                {{ $provider }}
                                                <br>
                                                <span class="muted">{{ $method }}</span>

                                                @if (!empty($hostOrderId))
                                                    <br>
                                                    <span class="muted">metadata.id_order:
                                                        #{{ $hostOrderId }}</span>
                                                @endif
                                            </td>

                                            <td>
                                                ${{ number_format($amount / 100, 2) }} {{ $currency }}
                                            </td>

                                            <td>
                                                <span class="pill {{ $status }}">
                                                    {{ $status }}
                                                </span>
                                                <br>
                                                <span class="muted">
                                                    provider_status: {{ $providerStatus ?: '—' }}
                                                </span>

                                                @if (!empty($metadata['mercado_pago_refund_status']))
                                                    <br>
                                                    <span class="muted">
                                                        refund_status:
                                                        {{ $metadata['mercado_pago_refund_status'] }}
                                                    </span>
                                                @endif
                                            </td>

                                            <td>
                                                <strong>external:</strong> {{ $externalReference ?: '—' }}
                                                <br>
                                                <strong>provider payment:</strong>
                                                {{ $providerPaymentId ?: '—' }}
                                                <br>
                                                <strong>provider order:</strong>
                                                {{ $providerOrderId ?: '—' }}
                                            </td>

                                            <td>
                                                <div class="actions">
                                                    <a class="button secondary"
                                                        href="{{ route('stag-herd.payments.show', $paymentId) }}">
                                                        Ver local
                                                    </a>

                                                    <form method="POST"
                                                        action="{{ route('stag-herd.payments.sync', $paymentId) }}">
                                                        @csrf
                                                        <button class="success" type="submit">
                                                            Actualizar
                                                        </button>
                                                    </form>

                                                    <form method="POST"
                                                        action="{{ route('stag-herd.payments.cancel', $paymentId) }}">
                                                        @csrf
                                                        <button class="warning" type="submit">
                                                            Cancelar
                                                        </button>
                                                    </form>

                                                    <form method="POST"
                                                        action="{{ route('stag-herd.payments.refund', $paymentId) }}">
                                                        @csrf
                                                        <button class="danger" type="submit">
                                                            Reembolsar
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt">
                            {{ $payments->links() }}
                        </div>
                    @endif
                </section>

                @if ($selectedPayment ?? null)
                    <section class="card">
                        <h2>Detalle local del pago</h2>

                        <p class="muted">
                            Esto no consulta Mercado Pago. Solo muestra lo guardado localmente.
                        </p>

                        <div class="table-wrap">
                            <pre>{{ json_encode($selectedPayment, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                        </div>
                    </section>
                @endif
            </section>
        </section>
    </main>

    <script>
        const providerSelect = document.getElementById('provider');
        const methodSelect = document.getElementById('method');

        const cashFields = document.querySelectorAll('.cash-fields');
        const mercadoPagoFields = document.querySelectorAll('.mercado-pago-fields');

        const amountInput = document.getElementById('amount');
        const currencyInput = document.getElementById('currency');
        const externalReferenceInput = document.getElementById('external_reference');
        const payerEmailInput = document.getElementById('payer_email');
        const descriptionInput = document.getElementById('description');

        const checkoutRoot = document.querySelector("[data-stag-herd-checkout='mercado-pago-card']");

        const methodsByProvider = {
            cash: [{
                value: 'cash',
                label: 'cash'
            }, ],
            mercado_pago: [{
                    value: 'card',
                    label: 'card / Payment Brick'
                },
                {
                    value: 'wallet',
                    label: 'wallet / account_money'
                },
            ],
        };

        function fillMethods(provider) {
            const methods = methodsByProvider[provider] || [];

            methodSelect.innerHTML = '';

            methods.forEach((method) => {
                const option = document.createElement('option');

                option.value = method.value;
                option.textContent = method.label;

                methodSelect.appendChild(option);
            });
        }

        function toggleProviderFields() {
            const provider = providerSelect.value;

            cashFields.forEach((element) => {
                element.classList.toggle('hidden', provider !== 'cash');
            });

            mercadoPagoFields.forEach((element) => {
                element.classList.toggle('hidden', provider !== 'mercado_pago');
            });

            syncCheckoutDataset();
        }

        function syncCheckoutDataset() {
            if (!checkoutRoot) {
                return;
            }

            checkoutRoot.dataset.amount = amountInput?.value || '120.00';
            checkoutRoot.dataset.currency = currencyInput?.value || 'MXN';
            checkoutRoot.dataset.externalReference = externalReferenceInput?.value || '';
            checkoutRoot.dataset.payerEmail = payerEmailInput?.value || 'cliente@test.com';
            checkoutRoot.dataset.description = descriptionInput?.value || 'Pago desde Mercado Pago Card Brick';
        }

        providerSelect.addEventListener('change', () => {
            fillMethods(providerSelect.value);
            toggleProviderFields();
        });

        methodSelect.addEventListener('change', toggleProviderFields);

        [
            amountInput,
            currencyInput,
            externalReferenceInput,
            payerEmailInput,
            descriptionInput,
        ].forEach((input) => {
            if (!input) {
                return;
            }

            input.addEventListener('input', syncCheckoutDataset);
            input.addEventListener('change', syncCheckoutDataset);
        });

        fillMethods(providerSelect.value);
        toggleProviderFields();
    </script>
</body>

</html>
