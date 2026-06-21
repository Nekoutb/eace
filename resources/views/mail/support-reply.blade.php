<x-mail.layout :title="__('support.reply_mail.subject')">
    <p style="margin:0 0 14px;font-size:14px;line-height:1.6;">{{ __('support.reply_mail.intro') }}</p>

    @if ($question)
        <p style="margin:0 0 6px;font-size:12px;color:#46685a;">{{ __('support.reply_mail.your_question') }}</p>
        <p style="margin:0 0 18px;font-size:13px;color:#46685a;font-style:italic;">{{ $question }}</p>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 18px;background:#f0fdf4;border:1px solid rgba(21,128,61,.18);border-radius:12px;">
        <tr><td style="padding:14px 16px;font-size:14px;line-height:1.6;color:#0f231a;white-space:pre-wrap;">{{ $body }}</td></tr>
    </table>

    <p style="margin:0;font-size:13px;line-height:1.6;color:#46685a;">{{ __('support.reply_mail.signoff') }}</p>
</x-mail.layout>
