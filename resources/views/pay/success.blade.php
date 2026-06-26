<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment received — {{ $businessName }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center;
            background: #0b1220; color: #e7edf7;
            font: 16px/1.6 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        .card { max-width: 520px; text-align: center; padding: 2.5rem;
            background: #131c2e; border: 1px solid #22c55e; border-radius: 1rem; }
        .check { width: 64px; height: 64px; border-radius: 50%; margin: 0 auto 1rem;
            background: rgba(34,197,94,.15); color: #22c55e; display: grid; place-items: center;
            font-size: 2rem; font-weight: 800; }
        h1 { margin: 0 0 .5rem; font-size: 1.6rem; }
        p { color: #93a1b8; }
    </style>
</head>
<body>
<div class="card">
    <div class="check">✓</div>
    <h1>Thank you — payment received.</h1>
    <p>Your payment for invoice {{ $invoice->number }} to {{ $businessName }} is complete.
        A receipt has been emailed to you by Stripe. You can close this page.</p>
</div>
</body>
</html>
