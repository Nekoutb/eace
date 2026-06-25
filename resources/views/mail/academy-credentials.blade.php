<x-mail.layout :title="__('academy.mail_subject')">
    <p style="margin:0 0 14px;font-size:16px;font-weight:700;color:#166534;">{{ __('academy.mail_greeting', ['name' => $name]) }}</p>

    <p style="margin:0 0 18px;font-size:14px;line-height:1.6;">{{ __('academy.mail_intro') }}</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 18px;background:#f0fdf4;border:1px solid rgba(21,128,61,.18);border-radius:12px;">
        <tr><td style="padding:14px 16px;font-size:13px;line-height:1.7;color:#0f231a;">
            <strong style="color:#166534;">{{ __('academy.cred_email') }}:</strong>
            <code style="font-size:13px;">{{ $email }}</code><br>
            <strong style="color:#166534;">{{ __('academy.cred_password') }}:</strong>
            <code style="font-size:13px;">{{ $temporaryPassword }}</code>
        </td></tr>
    </table>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 18px;">
        <tr>
            <td style="border-radius:12px;background:#16a34a;">
                <a href="{{ $loginUrl }}" style="display:inline-block;padding:12px 22px;color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;border-radius:12px;">
                    {{ __('academy.mail_cta') }}
                </a>
            </td>
        </tr>
    </table>

    <p style="margin:0;font-size:13px;line-height:1.6;color:#46685a;">{{ __('academy.mail_note') }}</p>
</x-mail.layout>
