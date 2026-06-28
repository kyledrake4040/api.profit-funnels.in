<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Invoice {{ $invoice->number }} from {{ $businessName }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;font-size:16px;line-height:1.6;color:#1a2236;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:40px 20px;">
  <tr><td align="center">
    <table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;border:1px solid #dde3ef;">
      <!-- Header -->
      <tr><td style="background:#131c2e;padding:28px 40px;">
        <div style="font-size:20px;font-weight:800;color:#ffffff;">{{ $businessName }}</div>
        <div style="font-size:13px;color:#93a1b8;margin-top:4px;">Invoice {{ $invoice->number }}</div>
      </td></tr>
      <!-- Body -->
      <tr><td style="padding:36px 40px;">
        <p style="margin:0 0 24px;color:#4a5568;">
          Hi{{ $invoice->contact ? ', ' . $invoice->contact->first_name : '' }}! Please find your invoice below.
        </p>

        <!-- Line items -->
        <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-bottom:24px;">
          <thead>
            <tr style="border-bottom:2px solid #e2e8f0;">
              <th style="text-align:left;padding:8px 0;font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;">Description</th>
              <th style="text-align:right;padding:8px 0;font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;">Qty</th>
              <th style="text-align:right;padding:8px 0;font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;">Amount</th>
            </tr>
          </thead>
          <tbody>
            @forelse($invoice->items as $item)
            <tr style="border-bottom:1px solid #f0f4f8;">
              <td style="padding:10px 0;color:#1a2236;">{{ $item->description }}</td>
              <td style="padding:10px 0;text-align:right;color:#6b7280;">{{ rtrim(rtrim(number_format((float)$item->quantity, 2),'0'),'.') }}</td>
              <td style="padding:10px 0;text-align:right;color:#1a2236;">{{ strtoupper($invoice->currency) }} {{ number_format((float)$item->quantity * (float)$item->unit_price, 2) }}</td>
            </tr>
            @empty
            <tr><td colspan="3" style="padding:10px 0;color:#9ca3af;">No line items.</td></tr>
            @endforelse
          </tbody>
          <tfoot>
            <tr>
              <td colspan="2" style="padding:16px 0 0;font-weight:700;color:#1a2236;font-size:15px;">Total due</td>
              <td style="padding:16px 0 0;text-align:right;font-weight:800;font-size:20px;color:#1a2236;">{{ strtoupper($invoice->currency) }} {{ number_format((float)$invoice->total, 2) }}</td>
            </tr>
          </tfoot>
        </table>

        @if($invoice->due_at)
        <p style="margin:0 0 28px;color:#6b7280;font-size:14px;">Due by: <strong>{{ $invoice->due_at->format('F j, Y') }}</strong></p>
        @endif

        <div style="text-align:center;margin:28px 0;">
          <a href="{{ $payUrl }}" style="display:inline-block;padding:16px 32px;background:#22c55e;color:#052e16;font-weight:800;font-size:16px;border-radius:6px;text-decoration:none;">Pay Invoice Online →</a>
        </div>

        <p style="margin:0;color:#6b7280;font-size:13px;text-align:center;">Secure payment powered by Stripe. You can also view and pay this invoice at:<br>
        <a href="{{ $payUrl }}" style="color:#2563eb;">{{ $payUrl }}</a></p>
      </td></tr>
      <!-- Footer -->
      <tr><td style="padding:20px 40px;border-top:1px solid #dde3ef;color:#9ca3af;font-size:12px;">
        Questions about this invoice? Reply directly to this email.
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
