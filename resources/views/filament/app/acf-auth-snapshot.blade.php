@php($acf = $getRecord())
@php($snap = ($acf?->authenticity_snapshot_path && \Illuminate\Support\Facades\Storage::disk('local')->exists($acf->authenticity_snapshot_path)) ? 'data:image/png;base64,'.base64_encode((string) \Illuminate\Support\Facades\Storage::disk('local')->get($acf->authenticity_snapshot_path)) : null)

@if ($snap)
    <a href="{{ $snap }}" target="_blank" rel="noopener" title="{{ __('app.ui.ev_view_snapshot') }}" style="display:inline-block">
        <img src="{{ $snap }}" alt="{{ __('app.ui.ev_view_snapshot') }}" style="max-width:340px;width:100%;height:auto;border-radius:10px;border:1px solid rgba(21,128,61,.2)" />
    </a>
    <a href="{{ $snap }}" download="acf-authentication-{{ $acf?->reference ?? 'snapshot' }}.png" style="display:inline-flex;align-items:center;gap:.3rem;margin-top:.5rem;font-size:.82rem;font-weight:600;color:#15803d;text-decoration:none">
        @svg('heroicon-o-arrow-down-tray', '', ['style' => 'width:1.05em;height:1.05em']) {{ __('app.ui.download_snapshot') }}
    </a>
@else
    <span style="color:#6b7280;font-size:.85rem">{{ __('app.ui.no_snapshot_yet') }}</span>
@endif
