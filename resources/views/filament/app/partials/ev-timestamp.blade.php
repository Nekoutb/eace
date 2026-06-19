{{-- Trusted-timestamp seal for one evidence capture. Expects $ts: ?EvidenceTimestamp. --}}
@php($ts = $ts ?? null)
@if ($ts && $ts->isSigned())
    <span class="ev-badge ev-badge--info" style="margin-top:.35rem" title="SHA-256 {{ $ts->sha256 }}">
        @svg('heroicon-m-shield-check', 'ev-ico', ['aria-hidden' => 'true']){{ __('app.timestamp.sealed') }}
    </span>
    <span class="ev-muted" style="display:block;margin-top:.2rem">
        {{ $ts->timestamped_at?->format('d/m/Y H:i:s') ?? '—' }}
        @if (filled($ts->tsa_url)) · {{ \Illuminate\Support\Str::of((string) $ts->tsa_url)->after('//')->before('/') }} @endif
    </span>
    @if (filled($ts->token_path) && \Illuminate\Support\Facades\Storage::disk($ts->disk)->exists($ts->token_path))
        <a href="data:application/timestamp-reply;base64,{{ base64_encode((string) \Illuminate\Support\Facades\Storage::disk($ts->disk)->get($ts->token_path)) }}"
           download="evidence-timestamp.tsr" class="ev-link" style="margin-top:.2rem"
           title="openssl ts -verify -in evidence-timestamp.tsr -digest {{ $ts->sha256 }} -CAfile <tsa-ca.pem>">@svg('heroicon-o-arrow-down-tray', 'ev-ico', ['aria-hidden' => 'true']){{ __('app.timestamp.download_token') }}</a>
    @endif
@elseif ($ts && $ts->status === \App\Domain\Evidence\TimestampStatus::Pending)
    <span class="ev-pending" style="margin-top:.35rem">@svg('heroicon-m-clock', 'ev-ico', ['aria-hidden' => 'true']){{ __('app.timestamp.pending') }}</span>
@endif
