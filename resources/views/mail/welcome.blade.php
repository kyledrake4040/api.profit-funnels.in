<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Welcome to Maritime Geo</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;font-size:16px;line-height:1.6;color:#1a2236;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:40px 20px;">
  <tr><td align="center">
    <table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;border:1px solid #dde3ef;">
      <!-- Header -->
      <tr><td style="background:#131c2e;padding:28px 40px;">
        <div style="font-size:22px;font-weight:800;color:#ffffff;letter-spacing:-.02em;">Maritime Geo</div>
        <div style="font-size:13px;color:#93a1b8;margin-top:4px;">See which marketing actually books you paid jobs.</div>
      </td></tr>
      <!-- Body -->
      <tr><td style="padding:36px 40px;">
        <h1 style="margin:0 0 8px;font-size:22px;font-weight:700;color:#1a2236;">Welcome, {{ $user->name }}!</h1>
        <p style="margin:0 0 20px;color:#4a5568;">Your Maritime Geo subscription is active. To access your dashboard and start tracking which marketing books you paid jobs, set your password below — then sign in at <a href="{{ url('/app') }}" style="color:#2563eb;">app.maritimegeo.ca</a>.</p>
        <p style="margin:0 0 24px;color:#4a5568;">Your login email: <strong>{{ $user->email }}</strong></p>

        <div style="text-align:center;margin:32px 0;">
          <a href="{{ $resetLink }}" style="display:inline-block;padding:14px 28px;background:#22c55e;color:#052e16;font-weight:800;font-size:15px;border-radius:6px;text-decoration:none;">Set your password &amp; log in →</a>
        </div>

        <p style="margin:0;color:#6b7280;font-size:13px;">This link expires in 60 minutes. If it expires, use the <a href="{{ url('/password/reset') }}" style="color:#2563eb;">forgot password</a> page to generate a new one.</p>
      </td></tr>
      <!-- What's next -->
      <tr><td style="padding:0 40px 36px;">
        <div style="background:#f8fafc;border-radius:6px;padding:20px 24px;border:1px solid #e2e8f0;">
          <div style="font-weight:700;color:#1a2236;margin-bottom:12px;">What you can do right now</div>
          <ul style="margin:0;padding-left:20px;color:#4a5568;line-height:2;">
            <li>Add your contacts and build your pipeline</li>
            <li>Schedule jobs and track completion</li>
            <li>Create quotes → convert to invoices → send clients a pay link</li>
            <li>Set up automations ("when deal won → book a job")</li>
            <li>Get AI insights on what to do today</li>
          </ul>
        </div>
      </td></tr>
      <!-- Footer -->
      <tr><td style="padding:20px 40px;border-top:1px solid #dde3ef;color:#9ca3af;font-size:12px;">
        Maritime Geo · <a href="{{ url('/') }}" style="color:#9ca3af;">maritimegeo.ca</a><br>
        Questions? Reply to this email and we'll help you get set up.
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
