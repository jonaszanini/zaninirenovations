<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $title ?? config('mail.from.name') }}</title>
</head>
<body style="margin:0;padding:0;background:#f6f7f9;font-family:Arial, Helvetica, sans-serif;color:#1f2937;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f7f9;padding:24px 0;">
    <tr>
      <td align="center">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;max-width:600px;width:100%;box-shadow:0 1px 3px rgba(0,0,0,0.06);">
          <tr>
            <td style="background:#024768;color:#ffffff;padding:20px 28px;font-size:18px;font-weight:600;">
              {{ config('mail.from.name', 'Zanini Renovations') }}
            </td>
          </tr>
          <tr>
            <td style="padding:28px;font-size:15px;line-height:1.55;color:#1f2937;">
              {!! $slot !!}
            </td>
          </tr>
          <tr>
            <td align="center" style="background:#ffffff;padding:20px 28px 8px 28px;">
              <img src="{{ asset('images/logo.png') }}"
                   alt="{{ config('mail.from.name', 'Zanini Renovations') }}"
                   width="160"
                   style="display:block;max-width:160px;height:auto;border:0;outline:none;text-decoration:none;">
            </td>
          </tr>
          <tr>
            <td style="background:#f1f5f9;color:#6b7280;padding:16px 28px;font-size:12px;line-height:1.4;text-align:center;">
              &copy; {{ date('Y') }} {{ config('mail.from.name', 'Zanini Renovations') }}. This is an automated message.
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
