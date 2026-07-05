<x-mail.layout :title="__('monitoring.mail_heading')">
    <p style="margin:0 0 16px;font-size:14px;line-height:1.6;">{{ __('monitoring.mail_intro') }}</p>

    @if (count($overdue))
        <p style="margin:14px 0 4px;font-size:13px;font-weight:800;color:#b91c1c;text-transform:uppercase;letter-spacing:.03em;">{{ __('monitoring.overdue') }} ({{ count($overdue) }})</p>
        @foreach ($overdue as $r)
            <div style="font-size:13px;line-height:1.5;padding:5px 0;border-bottom:1px solid #f0f0ee;"><strong style="color:#111;">{{ $r['name'] }}</strong> — {{ $r['detail'] }}</div>
        @endforeach
    @endif

    @if (count($dueSoon))
        <p style="margin:16px 0 4px;font-size:13px;font-weight:800;color:#b45309;text-transform:uppercase;letter-spacing:.03em;">{{ __('monitoring.due_soon') }} ({{ count($dueSoon) }})</p>
        @foreach ($dueSoon as $r)
            <div style="font-size:13px;line-height:1.5;padding:5px 0;border-bottom:1px solid #f0f0ee;"><strong style="color:#111;">{{ $r['name'] }}</strong> — {{ $r['detail'] }}</div>
        @endforeach
    @endif

    @if (count($down))
        <p style="margin:16px 0 4px;font-size:13px;font-weight:800;color:#b91c1c;text-transform:uppercase;letter-spacing:.03em;">{{ __('monitoring.sites_down') }} ({{ count($down) }})</p>
        @foreach ($down as $r)
            <div style="font-size:13px;line-height:1.5;padding:5px 0;border-bottom:1px solid #f0f0ee;"><strong style="color:#111;">{{ $r['name'] }}</strong> — {{ $r['detail'] }}</div>
        @endforeach
    @endif

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:22px 0 6px;">
        <tr><td style="border-radius:12px;background:#111111;">
            <a href="{{ rtrim(config('app.url'), '/') }}/admin" style="display:inline-block;padding:12px 22px;color:#fff;font-size:14px;font-weight:700;text-decoration:none;border-radius:12px;">{{ __('monitoring.mail_cta') }}</a>
        </td></tr>
    </table>
    <p style="margin:6px 0 0;font-size:12px;color:#777;">{{ __('monitoring.mail_note') }}</p>
</x-mail.layout>
