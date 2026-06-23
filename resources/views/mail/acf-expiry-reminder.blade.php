<x-mail.layout :title="'ACF '.$acfReference">
    @php($date = $expiresAt->format('d/m/Y'))

    {{-- ─────────────  Français  ───────────── --}}
    <p style="margin:0 0 4px;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#15803d;">Français</p>
    <p style="margin:0 0 12px;font-size:15px;font-weight:700;color:#166534;">@if(filled($taxpayerName))Bonjour {{ $taxpayerName }},@else Madame, Monsieur,@endif</p>
    <p style="margin:0 0 14px;font-size:14px;line-height:1.7;color:#0f231a;">
        Nous vous informons que votre <strong>Attestation de Conformité Fiscale</strong>
        (référence&nbsp;<code>{{ $acfReference }}</code>)
        @if($daysLeft < 0)
            est arrivée à expiration le <strong>{{ $date }}</strong>.
        @elseif($daysLeft === 0)
            <strong>expire aujourd’hui</strong> ({{ $date }}).
        @else
            arrive à échéance le <strong>{{ $date }}</strong> (dans {{ $daysLeft }}&nbsp;jour{{ $daysLeft > 1 ? 's' : '' }}).
        @endif
        Nous vous invitons à entreprendre son renouvellement auprès de la DGI afin d’éviter toute interruption dans vos opérations.
    </p>

    {{-- ─────────────  English  ───────────── --}}
    <div style="margin:18px 0;border-top:1px solid rgba(21,128,61,.14);"></div>
    <p style="margin:0 0 4px;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#15803d;">English</p>
    <p style="margin:0 0 12px;font-size:15px;font-weight:700;color:#166534;">@if(filled($taxpayerName))Dear {{ $taxpayerName }},@else Dear Sir or Madam,@endif</p>
    <p style="margin:0 0 14px;font-size:14px;line-height:1.7;color:#0f231a;">
        This is a reminder that your <strong>Tax Clearance Certificate</strong> (ACF,
        reference&nbsp;<code>{{ $acfReference }}</code>)
        @if($daysLeft < 0)
            expired on <strong>{{ $date }}</strong>.
        @elseif($daysLeft === 0)
            <strong>expires today</strong> ({{ $date }}).
        @else
            expires on <strong>{{ $date }}</strong> (in {{ $daysLeft }}&nbsp;day{{ $daysLeft > 1 ? 's' : '' }}).
        @endif
        Please arrange its renewal with the DGI to avoid any interruption to your operations.
    </p>

    {{-- Optional personal note from the sender --}}
    @if(filled($note))
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:6px 0 4px;background:#f0fdf4;border:1px solid rgba(21,128,61,.18);border-radius:12px;">
            <tr><td style="padding:12px 16px;font-size:13px;line-height:1.7;color:#0f231a;white-space:pre-line;">{{ $note }}</td></tr>
        </table>
    @endif

    {{-- Signature — the EACE user who sent it (replies route here) --}}
    <p style="margin:20px 0 0;font-size:13px;line-height:1.6;color:#46685a;">
        {{ $senderName }}<br>
        <a href="mailto:{{ $senderEmail }}" style="color:#15803d;">{{ $senderEmail }}</a>
    </p>
</x-mail.layout>
