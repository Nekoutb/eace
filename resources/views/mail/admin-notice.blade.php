<x-mail.layout :title="__('app.mail.admin_notice.subject')">
    <p style="margin:0 0 14px;font-size:15px;font-weight:700;color:#166534;">{{ __('app.mail.admin_notice.title') }}</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 8px;background:#f0fdf4;border:1px solid rgba(21,128,61,.18);border-radius:12px;">
        <tr><td style="padding:14px 16px;font-size:13px;line-height:1.7;color:#0f231a;">
            <strong style="color:#166534;">{{ __('app.mail.admin_notice.org_label') }}:</strong> {{ $orgName }}<br>
            <strong style="color:#166534;">{{ __('app.mail.admin_notice.email_label') }}:</strong> <code>{{ $email }}</code><br>
            <strong style="color:#166534;">{{ __('app.mail.admin_notice.workspace_label') }}:</strong> <code>{{ $tenantId }}</code>
        </td></tr>
    </table>
</x-mail.layout>
