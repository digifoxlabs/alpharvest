<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment {{ $payment->reference }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 20px;
            font-family: Georgia, "Times New Roman", serif;
            background:
                radial-gradient(circle at top left, rgba(36, 94, 76, 0.18), transparent 25%),
                linear-gradient(135deg, #ede3d0 0%, #f6f2eb 50%, #dce8de 100%);
            color: #173128;
        }
        .checkout {
            width: min(560px, 100%);
            background: rgba(255, 255, 255, 0.82);
            border-radius: 30px;
            padding: 30px;
            box-shadow: 0 28px 70px rgba(30, 52, 44, 0.14);
        }
        .status {
            display: inline-flex;
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(23, 49, 40, 0.08);
            margin-bottom: 18px;
            font-family: "Trebuchet MS", sans-serif;
        }
        h1, h2, p { margin: 0; }
        .meta {
            display: grid;
            gap: 12px;
            margin: 24px 0;
            font-family: "Trebuchet MS", sans-serif;
        }
        .row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(23, 49, 40, 0.1);
        }
        .button {
            width: 100%;
            border: none;
            border-radius: 18px;
            padding: 16px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 700;
            background: #1c8a5f;
            color: white;
        }
        .button[disabled] {
            background: #658f7e;
            cursor: default;
        }
    </style>
</head>
<body>
    <section class="checkout">
        <div class="status">{{ strtoupper($payment->status) }}</div>
        <h1>{{ $payment->order->store->name }}</h1>
        <p style="margin-top: 10px;">Complete payment for order <strong>{{ $payment->order->order_number }}</strong>.</p>

        @if (session('status'))
            <p style="margin-top: 16px; color: #1c8a5f; font-family: 'Trebuchet MS', sans-serif;">{{ session('status') }}</p>
        @endif

        <div class="meta">
            <div class="row"><span>Customer</span><strong>{{ $payment->order->customer->name ?: $payment->order->customer->phone }}</strong></div>
            <div class="row"><span>Reference</span><strong>{{ $payment->reference }}</strong></div>
            <div class="row"><span>Amount</span><strong>{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</strong></div>
            <div class="row"><span>Provider</span><strong>{{ $payment->provider }}</strong></div>
        </div>

        <form method="POST" action="{{ route('payments.confirm', $payment) }}">
            @csrf
            <button class="button" type="submit" @if ($payment->status === 'paid') disabled @endif>
                @if ($payment->status === 'paid')
                    Payment already confirmed
                @else
                    Confirm payment
                @endif
            </button>
        </form>
    </section>
</body>
</html>
