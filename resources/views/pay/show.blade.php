<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice {{ $invoice->number }} — {{ $businessName }}</title>
    <style>
        :root { --bg:#0b1220; --card:#131c2e; --line:#23304a; --ink:#e7edf7; --muted:#93a1b8; --green:#22c55e; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; padding: 2rem 1rem; background: var(--bg); color: var(--ink);
            font: 16px/1.6 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            display: grid; place-items: start center; }
        .card { width: 100%; max-width: 560px; background: var(--card);
            border: 1px solid var(--line); border-radius: 1rem; overflow: hidden; }
        .head { padding: 1.6rem 1.8rem; border-bottom: 1px solid var(--line); }
        .biz { font-size: 1.25rem; font-weight: 800; }
        .num { color: var(--muted); font-size: .92rem; margin-top: .15rem; }
        .body { padding: 1.4rem 1.8rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: .55rem 0; border-bottom: 1px solid var(--line); }
        th { color: var(--muted); font-weight: 600; font-size: .8rem; text-transform: uppercase; letter-spacing: .03em; }
        td.r, th.r { text-align: right; }
        .total { display: flex; justify-content: space-between; align-items: baseline;
            margin-top: 1.2rem; padding-top: 1rem; }
        .total .amt { font-size: 1.9rem; font-weight: 800; }
        .muted { color: var(--muted); }
        .pill { display: inline-block; padding: .2rem .65rem; border-radius: 999px; font-size: .8rem; font-weight: 700; }
        .pill.Paid { background: rgba(34,197,94,.15); color: var(--green); }
        .pill.Sent, .pill.Draft { background: rgba(147,161,184,.15); color: var(--muted); }
        .pay { display: block; width: 100%; margin-top: 1.4rem; padding: .95rem 1rem; border: 0;
            border-radius: .6rem; background: var(--green); color: #052e16; font-size: 1.05rem;
            font-weight: 800; text-align: center; text-decoration: none; cursor: pointer; }
        .paidbox { margin-top: 1.4rem; padding: 1rem; border-radius: .6rem; text-align: center;
            background: rgba(34,197,94,.1); border: 1px solid var(--green); color: var(--green); font-weight: 700; }
        .notice { margin-top: 1.2rem; padding: .85rem 1rem; border-radius: .6rem;
            background: rgba(234,179,8,.1); border: 1px solid #eab308; color: #fde68a; font-size: .92rem; }
        .foot { padding: 1rem 1.8rem; color: var(--muted); font-size: .82rem; text-align: center; }
        .secure { color: var(--muted); font-size: .82rem; text-align: center; margin-top: .8rem; }
    </style>
</head>
<body>
<div class="card">
    <div class="head">
        <div class="biz">{{ $businessName }}</div>
        <div class="num">Invoice {{ $invoice->number }}@if($invoice->contact) · for {{ $invoice->contact->name }}@endif</div>
    </div>
    <div class="body">
        <table>
            <thead>
                <tr><th>Description</th><th class="r">Qty</th><th class="r">Amount</th></tr>
            </thead>
            <tbody>
                @forelse($invoice->items as $item)
                    <tr>
                        <td>{{ $item->description }}</td>
                        <td class="r muted">{{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }}</td>
                        <td class="r">{{ strtoupper($invoice->currency) }} {{ number_format((float) $item->quantity * (float) $item->unit_price, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="muted">No line items.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="total">
            <span class="muted">Total due</span>
            <span class="amt">{{ strtoupper($invoice->currency) }} {{ number_format((float) $invoice->total, 2) }}</span>
        </div>

        @if($invoice->isPaid())
            <div class="paidbox">✓ Paid — thank you!</div>
        @elseif(session('pay_unavailable'))
            <div class="notice">Online payment isn't switched on for this invoice yet. Please contact {{ $businessName }} to arrange payment.</div>
        @elseif($configured)
            <a class="pay" href="{{ route('pay.checkout', $invoice->pay_token) }}">Pay {{ strtoupper($invoice->currency) }} {{ number_format((float) $invoice->total, 2) }}</a>
            <div class="secure">🔒 Secure payment powered by Stripe</div>
        @else
            <div class="notice">Online payment isn't switched on for this invoice yet. Please contact {{ $businessName }} to arrange payment.</div>
        @endif
    </div>
    <div class="foot">Questions about this invoice? Reply to {{ $businessName }} directly.</div>
</div>
</body>
</html>
