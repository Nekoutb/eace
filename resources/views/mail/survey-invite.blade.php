<x-mail.layout :title="__('support.survey.subject')">
    <p style="margin:0 0 14px;font-size:16px;font-weight:700;color:#166534;">{{ __('support.survey.greeting', ['name' => $name]) }}</p>

    <p style="margin:0 0 18px;font-size:14px;line-height:1.6;">
        {{ __('support.survey.intro', ['org' => $orgName]) }}
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 18px;">
        <tr>
            <td style="border-radius:12px;background:#16a34a;">
                <a href="{{ $surveyUrl }}" style="display:inline-block;padding:12px 22px;color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;border-radius:12px;">
                    {{ __('support.survey.cta') }}
                </a>
            </td>
        </tr>
    </table>

    <p style="margin:0;font-size:13px;line-height:1.6;color:#46685a;">
        {{ __('support.survey.note') }}
    </p>
</x-mail.layout>
