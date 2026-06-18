@php($record = $getRecord())
@php($snapshot = $record->snapshot())
@php($acf = $record->acfCertificate)
@php($payload = $snapshot?->payload ?? [])
@php($onFile = (bool) ($payload['on_active_file'] ?? false))
@php($statusValue = $payload['fiscalis_status'] ?? null)
@php($acfUrl = $acf !== null ? \App\Filament\App\Resources\AcfCertificates\AcfCertificateResource::getUrl('view', ['record' => $acf]) : null)
@php($filesUrl = \App\Filament\App\Resources\TaxpayerFiles\TaxpayerFileResource::getUrl())
@php($acfAuth = $acf?->authenticity_status)
@php($acfSnap = ($acf && $acf->authenticity_snapshot_path && \Illuminate\Support\Facades\Storage::disk('local')->exists($acf->authenticity_snapshot_path)) ? 'data:image/png;base64,'.base64_encode((string) \Illuminate\Support\Facades\Storage::disk('local')->get($acf->authenticity_snapshot_path)) : null)
@php($fiscPresence = $record->fiscalis_presence)
@php($fiscSnap = ($record->fiscalis_snapshot_path && \Illuminate\Support\Facades\Storage::disk('local')->exists($record->fiscalis_snapshot_path)) ? 'data:image/png;base64,'.base64_encode((string) \Illuminate\Support\Facades\Storage::disk('local')->get($record->fiscalis_snapshot_path)) : null)

<div class="ev" x-data="{ zoom: null }">
    <style>
        [x-cloak]{display:none!important}
        .ev-zoom{cursor:zoom-in}
        .ev-lightbox{position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;
            background:rgba(15,23,42,.86);padding:2rem;cursor:zoom-out;overflow:auto}
        .ev-lightbox__img{max-width:96vw;max-height:92vh;width:auto;height:auto;border-radius:8px;
            box-shadow:0 20px 60px rgba(0,0,0,.5);background:#fff}
        .ev-lightbox__hint{position:fixed;top:1rem;right:1.25rem;color:#fff;font-size:.8rem;opacity:.85}
        .ev{--g100:#dcfce7;--g200:#bbf7d0;--g500:#22c55e;--g600:#16a34a;--g700:#15803d;--g800:#166534;
            --ink:#0f231a;--ink-soft:#46685a;--line:rgba(21,128,61,.16);color:var(--ink);
            font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif}
        .ev svg.ev-ico{width:1.05em;height:1.05em;flex:0 0 auto;vertical-align:-.15em}
        .ev-mono{font-family:ui-monospace,Menlo,Consolas,monospace;letter-spacing:.02em}
        .ev-head{display:flex;flex-wrap:wrap;gap:.55rem;margin-bottom:1.1rem}
        .ev-kv{flex:1 1 9rem;min-width:8.5rem;padding:.6rem .75rem;border-radius:12px;background:rgba(255,255,255,.6);border:1px solid var(--line)}
        .ev-k{display:block;font-size:.66rem;text-transform:uppercase;letter-spacing:.04em;color:var(--ink-soft);margin-bottom:.28rem}
        .ev-v{font-size:.92rem;font-weight:700;color:var(--ink)}
        .ev-h{display:flex;align-items:center;gap:.45rem;font-size:.9rem;font-weight:700;color:var(--g800);margin:1.25rem 0 .55rem}
        .ev-wrap{overflow-x:auto;border-radius:13px;border:1px solid var(--line);background:rgba(255,255,255,.45)}
        .ev-table{width:100%;border-collapse:collapse;font-size:.85rem}
        .ev-table th{text-align:left;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--g800);
            background:rgba(220,252,231,.7);padding:.55rem .8rem;white-space:nowrap;border-bottom:1px solid var(--line)}
        .ev-table td{padding:.55rem .8rem;border-bottom:1px solid rgba(21,128,61,.08);vertical-align:middle}
        .ev-table tr:last-child td{border-bottom:0}
        .ev-table tbody tr:hover{background:rgba(240,253,244,.7)}
        .ev-ta-c{text-align:center}
        .ev-strong{font-weight:600;color:var(--ink)}
        .ev-muted{color:var(--ink-soft);font-size:.82rem}
        .ev-tag{display:inline-block;font-size:.7rem;font-weight:600;color:var(--ink-soft);background:rgba(255,255,255,.7);
            border:1px solid var(--line);padding:.1rem .5rem;border-radius:7px;white-space:nowrap}
        .ev-pending{display:inline-flex;align-items:center;gap:.25rem;font-size:.7rem;font-weight:600;color:#92400e;
            background:#fef3c7;border:1px solid #fde68a;padding:.1rem .5rem;border-radius:999px}
        .ev-pill{display:inline-flex;align-items:center;gap:.28rem;font-size:.74rem;font-weight:700;padding:.18rem .5rem;border-radius:999px}
        .ev-pill--ok{color:var(--g800);background:var(--g100);border:1px solid var(--g200)}
        .ev-pill--no{color:#991b1b;background:#fee2e2;border:1px solid #fecaca}
        .ev-pill--idle{color:#475569;background:#f1f5f9;border:1px solid #e2e8f0}
        .ev-badge{display:inline-flex;align-items:center;gap:.28rem;font-size:.78rem;font-weight:700;padding:.2rem .55rem;border-radius:999px}
        .ev-badge--success{color:var(--g800);background:var(--g100);border:1px solid var(--g200)}
        .ev-badge--danger{color:#991b1b;background:#fee2e2;border:1px solid #fecaca}
        .ev-badge--warning{color:#92400e;background:#fef3c7;border:1px solid #fde68a}
        .ev-badge--suspended{color:#9a3412;background:#ffedd5;border:1px solid #fed7aa}
        .ev-badge--info{color:#115e59;background:#ccfbf1;border:1px solid #99f6e4}
        .ev-badge--gray{color:#475569;background:#f1f5f9;border:1px solid #e2e8f0}
        .ev-link{display:inline-flex;align-items:center;gap:.3rem;font-size:.8rem;font-weight:600;color:var(--g700);text-decoration:none;border-radius:7px}
        .ev-link:hover{color:var(--g800);text-decoration:underline}
        .ev-link:focus-visible{outline:none;box-shadow:0 0 0 3px rgba(34,197,94,.4)}
        @media (prefers-reduced-motion:reduce){.ev *{transition:none!important}}
        .dark .ev{--ink:#e8f6ee;--ink-soft:#9cc4ad;--line:rgba(134,239,172,.18)}
        .dark .ev-kv,.dark .ev-wrap{background:rgba(255,255,255,.05)}
        .dark .ev-table th{background:rgba(22,101,52,.4);color:var(--g200)}
    </style>

    {{-- Header strip --}}
    <div class="ev-head">
        @if ($record->approval)
            <div class="ev-kv">
                <span class="ev-k">{{ __('app.ui.approval_invoice') }}</span>
                <span class="ev-badge ev-badge--{{ $record->approval->color() }}">@svg($record->approval->icon(), 'ev-ico', ['aria-hidden' => 'true']){{ $record->approval->label() }}</span>
            </div>
        @endif
        <div class="ev-kv">
            <span class="ev-k">{{ __('app.ui.verdict') }}</span>
            <span class="ev-badge ev-badge--{{ $record->verdict->color() }}">@svg($record->verdict->icon(), 'ev-ico', ['aria-hidden' => 'true']){{ $record->verdict->label() }}</span>
        </div>
        <div class="ev-kv">
            <span class="ev-k">{{ __('app.ui.stage') }}</span>
            <span class="ev-v">{{ $record->stage->label() }}</span>
        </div>
        <div class="ev-kv">
            <span class="ev-k">{{ __('app.ui.niu') }}</span>
            <span class="ev-v ev-mono">{{ $record->niu ?? $record->raw_input }}</span>
        </div>
        <div class="ev-kv">
            <span class="ev-k">{{ __('app.ui.third_party') }}</span>
            <span class="ev-v">{{ $record->thirdParty?->name ?? '—' }}</span>
        </div>
        <div class="ev-kv">
            <span class="ev-k">{{ __('app.ui.exposure_estimated') }}</span>
            <span class="ev-v">{{ number_format((int) $record->exposure_xaf, 0, ',', ' ') }} <small style="font-weight:600;color:var(--ink-soft)">FCFA</small></span>
        </div>
        <div class="ev-kv">
            <span class="ev-k">{{ __('app.ui.verified_at') }}</span>
            <span class="ev-v">{{ $record->created_at?->format('d/m/Y H:i') ?? '—' }}</span>
        </div>
    </div>

    {{-- The four checks --}}
    @if (filled($record->checks))
        <h3 class="ev-h">@svg('heroicon-o-shield-check', 'ev-ico', ['aria-hidden' => 'true']){{ __('app.ui.section_four_checks') }}</h3>
        <div class="ev-wrap">
            <table class="ev-table">
                <thead><tr>
                    <th>{{ __('app.ui.check_col') }}</th>
                    <th>{{ __('app.ui.basis') }}</th>
                    <th class="ev-ta-c">{{ __('app.ui.result') }}</th>
                    <th>{{ __('app.ui.output') }}</th>
                </tr></thead>
                <tbody>
                @foreach ($record->checks as $c)
                    <tr>
                        <td class="ev-strong">{{ $c['label'] ?? '' }}</td>
                        <td><span class="ev-tag">{{ $c['article'] ?? '' }}</span></td>
                        <td class="ev-ta-c">@include('filament.app.partials.ev-pill', ['passed' => $c['passed'] ?? null])</td>
                        <td class="ev-muted">{{ $c['detail'] ?? '' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Invoice conformity (art. 150) --}}
    @if (filled($record->invoice_checks))
        <h3 class="ev-h">@svg('heroicon-o-receipt-percent', 'ev-ico', ['aria-hidden' => 'true']){{ __('app.ui.section_invoice_conformity') }}</h3>
        <div class="ev-wrap">
            <table class="ev-table">
                <thead><tr>
                    <th>{{ __('app.ui.check_col') }}</th>
                    <th>{{ __('app.ui.category') }}</th>
                    <th class="ev-ta-c">{{ __('app.ui.result') }}</th>
                    <th>{{ __('app.ui.output') }}</th>
                </tr></thead>
                <tbody>
                @foreach ($record->invoice_checks as $f)
                    <tr>
                        <td class="ev-strong">{{ $f['label'] ?? '' }}</td>
                        <td><span class="ev-tag">{{ __('app.conformity.type_'.($f['type'] ?? 'forme')) }}</span></td>
                        <td class="ev-ta-c">@include('filament.app.partials.ev-pill', ['passed' => $f['passed'] ?? null])</td>
                        <td class="ev-muted">{{ $f['detail'] ?? '' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Evidence gathered --}}
    <h3 class="ev-h">@svg('heroicon-o-folder-open', 'ev-ico', ['aria-hidden' => 'true']){{ __('app.audit.section_evidence') }}</h3>
    <div class="ev-wrap">
        <table class="ev-table">
            <thead><tr>
                <th>{{ __('app.audit.ev_item') }}</th>
                <th>{{ __('app.ui.status') }}</th>
                <th>{{ __('app.audit.ev_ref') }}</th>
            </tr></thead>
            <tbody>
                {{-- 1. Fiscalis snapshot showing the taxpayer active --}}
                <tr>
                    <td class="ev-strong">{{ __('app.audit.hub_ev_1') }}</td>
                    <td>
                        @if ($fiscPresence)
                            <span class="ev-badge ev-badge--{{ $fiscPresence->color() }}">{{ $fiscPresence->label() }}</span>
                            @if ($record->fiscalis_checked_at)
                                <span class="ev-muted" style="display:block;margin-top:.3rem">{{ __('app.ui.ev_verified_at') }} {{ $record->fiscalis_checked_at->format('d/m/Y H:i') }}</span>
                            @endif
                        @else
                            @if ($snapshot && is_string($statusValue))
                                <span class="ev-badge ev-badge--{{ $onFile ? 'success' : 'gray' }}">{{ __('app.fiscalis_status.'.$statusValue) }}</span>
                            @else
                                <span class="ev-tag">{{ __('app.ui.placeholder_dash') }}</span>
                            @endif
                            <span class="ev-pending">@svg('heroicon-m-clock', 'ev-ico', ['aria-hidden' => 'true']){{ __('app.audit.pending_live') }}</span>
                        @endif
                    </td>
                    <td>
                        @if ($fiscSnap)
                            <img src="{{ $fiscSnap }}" alt="{{ __('app.ui.ev_view_snapshot') }}" class="ev-zoom" @click="zoom = $el.src" title="{{ __('app.ui.ev_zoom_hint') }}" style="width:128px;height:auto;border-radius:8px;border:1px solid var(--line);display:block" />
                            <a href="{{ $fiscSnap }}" download="fiscalis-{{ $record->niu ?? 'snapshot' }}.png" class="ev-link" style="margin-top:.35rem">@svg('heroicon-o-arrow-down-tray', 'ev-ico', ['aria-hidden' => 'true']){{ __('app.ui.download_snapshot') }}</a>
                        @else
                            <span class="ev-mono ev-muted">{{ $snapshot ? \Illuminate\Support\Str::limit((string) $snapshot->content_hash, 16) : '—' }}</span>
                        @endif
                    </td>
                </tr>
                {{-- 2. Copy of the valid ACF --}}
                <tr>
                    <td class="ev-strong">{{ __('app.audit.hub_ev_2') }}</td>
                    <td><span class="ev-badge ev-badge--{{ $acf ? 'success' : 'gray' }}">{{ $acf ? __('app.audit.ev_stored') : __('app.audit.ev_absent') }}</span></td>
                    <td>
                        @if ($acfUrl)
                            <a href="{{ $acfUrl }}" class="ev-link">@svg('heroicon-o-document-check', 'ev-ico', ['aria-hidden' => 'true']){{ $acf->reference }}</a>
                        @else
                            <span class="ev-muted">—</span>
                        @endif
                    </td>
                </tr>
                {{-- 3. Source taxpayer file the NIU was confirmed in --}}
                <tr>
                    <td class="ev-strong">{{ __('app.audit.hub_ev_3') }}</td>
                    <td><span class="ev-tag">{{ $snapshot?->source ?? __('app.ui.placeholder_dash') }}</span></td>
                    <td><a href="{{ $filesUrl }}" class="ev-link">@svg('heroicon-o-arrow-down-tray', 'ev-ico', ['aria-hidden' => 'true']){{ __('app.audit.ev_open') }}</a></td>
                </tr>
                {{-- 4. Authenticated ACF snapshot (Harmony2) --}}
                <tr>
                    <td class="ev-strong">{{ __('app.audit.hub_ev_4') }}</td>
                    <td>
                        @if ($acfAuth)
                            <span class="ev-badge ev-badge--{{ $acfAuth->color() }}">{{ $acfAuth->label() }}</span>
                            @if ($acf?->last_verified_at)
                                <span class="ev-muted" style="display:block;margin-top:.3rem">{{ __('app.ui.ev_verified_at') }} {{ $acf->last_verified_at->format('d/m/Y H:i') }}</span>
                            @endif
                        @else
                            <span class="ev-pending">@svg('heroicon-m-clock', 'ev-ico', ['aria-hidden' => 'true']){{ __('app.audit.pending_harmony') }}</span>
                        @endif
                    </td>
                    <td>
                        @if ($acfSnap)
                            <img src="{{ $acfSnap }}" alt="{{ __('app.ui.ev_view_snapshot') }}" class="ev-zoom" @click="zoom = $el.src" title="{{ __('app.ui.ev_zoom_hint') }}" style="width:128px;height:auto;border-radius:8px;border:1px solid var(--line);display:block" />
                            <a href="{{ $acfSnap }}" download="acf-authentication-{{ $acf?->reference ?? 'snapshot' }}.png" class="ev-link" style="margin-top:.35rem">@svg('heroicon-o-arrow-down-tray', 'ev-ico', ['aria-hidden' => 'true']){{ __('app.ui.download_snapshot') }}</a>
                        @else
                            <span class="ev-muted">—</span>
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Triggered rules --}}
    @if (filled($record->triggered_rules))
        <h3 class="ev-h">@svg('heroicon-o-exclamation-triangle', 'ev-ico', ['aria-hidden' => 'true']){{ __('app.ui.section_triggered_rules') }}</h3>
        <div class="ev-wrap">
            <table class="ev-table">
                <thead><tr>
                    <th>{{ __('app.ui.rule') }}</th>
                    <th>{{ __('app.ui.basis') }}</th>
                    <th>{{ __('app.ui.exposure') }}</th>
                    <th>{{ __('app.ui.output') }}</th>
                </tr></thead>
                <tbody>
                @foreach ($record->triggered_rules as $r)
                    <tr>
                        <td class="ev-strong">{{ $r['title'] ?? '' }}</td>
                        <td><span class="ev-tag">{{ $r['article'] ?? '' }}</span></td>
                        <td>{{ number_format((int) ($r['exposure_xaf'] ?? 0), 0, ',', ' ') }} <small class="ev-muted">FCFA</small></td>
                        <td class="ev-muted">{{ $r['message'] ?? '' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Click-to-zoom lightbox: a snapshot opens here full-size; click anywhere or Esc to close. --}}
    <div class="ev-lightbox" x-cloak x-show="zoom" x-transition.opacity @click="zoom = null" @keydown.escape.window="zoom = null">
        <span class="ev-lightbox__hint">{{ __('app.ui.ev_zoom_close') }}</span>
        <img class="ev-lightbox__img" :src="zoom" alt="{{ __('app.ui.ev_view_snapshot') }}" />
    </div>
</div>
