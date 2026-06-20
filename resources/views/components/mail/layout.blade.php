@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>
</head>
<body style="margin:0;padding:0;background:#f0fdf4;font-family:ui-sans-serif,system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif;color:#0f231a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f0fdf4;padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border:1px solid rgba(21,128,61,.18);border-radius:16px;overflow:hidden;">
                    <tr>
                        <td style="background:#15803d;padding:18px 28px;">
                            <span style="color:#ffffff;font-size:16px;font-weight:800;letter-spacing:.2px;">Elite Advisors Compliance Engine</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            {{ $slot }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 28px;border-top:1px solid rgba(21,128,61,.12);color:#46685a;font-size:12px;line-height:1.5;">
                            {{ __('app.mail.footer') }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
