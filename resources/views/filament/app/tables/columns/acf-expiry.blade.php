@php
    /** @var \App\Models\AcfCertificate $record */
    $record = $getRecord();
    $alert = $record->expiryAlert();
    $days = $record->daysToExpiry();
    $date = $record->expires_at?->format('d/m/Y');

    $text = $days < 0
        ? trans_choice('app.acf_reminder.expired_ago', abs($days), ['count' => abs($days)])
        : ($days === 0
            ? __('app.acf_reminder.today')
            : trans_choice('app.acf_reminder.in_days', $days, ['count' => $days]));
@endphp

<div style="display:flex;align-items:center;gap:.5rem;white-space:nowrap;">
    <span class="niu-exp-dot {{ $alert->dotClass() }}"></span>
    <span style="display:flex;flex-direction:column;line-height:1.15;">
        <span style="font-weight:600;">{{ $text }}</span>
        @if($date)
            <span style="font-size:.72rem;color:#6b7280;">{{ $date }}</span>
        @endif
    </span>
</div>
