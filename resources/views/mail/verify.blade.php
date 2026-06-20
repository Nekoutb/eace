<x-mail.layout :title="__('app.mail.verify.subject')">
    <p style="margin:0 0 14px;font-size:16px;font-weight:700;color:#166534;">{{ __('app.mail.greeting', ['name' => $name]) }}</p>

    <p style="margin:0 0 18px;font-size:14px;line-height:1.6;">
        {{ __('app.mail.verify.intro', ['org' => $orgName]) }}
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 18px;">
        <tr>
            <td style="border-radius:12px;background:#16a34a;">
                <a href="{{ $verifyUrl }}" style="display:inline-block;padding:12px 22px;color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;border-radius:12px;">
                    {{ __('app.mail.verify.cta') }}
                </a>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 12px;font-size:13px;line-height:1.6;color:#46685a;">
        {{ __('app.mail.verify.expiry', ['hours' => $expiryHours]) }}
    </p>

    <p style="margin:0;font-size:12px;line-height:1.6;color:#46685a;">
        {{ __('app.mail.verify.fallback') }}<br>
        <a href="{{ $verifyUrl }}" style="color:#15803d;word-break:break-all;">{{ $verifyUrl }}</a>
    </p>
</x-mail.layout>
