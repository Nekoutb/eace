<x-filament-panels::page>
    <div class="niu-hub">
        <style>
            .niu-hub{--g100:#dcfce7;--g200:#bbf7d0;--g500:#22c55e;--g600:#16a34a;--g700:#15803d;--g800:#166534;--g900:#14532d;
                --ink:#0f231a;--ink-soft:#46685a;--line:rgba(21,128,61,.16);
                position:relative;z-index:0;color:var(--ink);display:flex;flex-direction:column;gap:1.1rem;
                font-family:inherit}
            .niu-hub::before{content:"";position:absolute;inset:-26px -18px;z-index:-1;border-radius:30px;pointer-events:none;
                background:radial-gradient(60% 70% at 10% -4%,rgba(134,239,172,.5),transparent 60%),linear-gradient(180deg,#f4fdf7,#ecfdf3)}
            .niu-hub__card{background:rgba(255,255,255,.93);backdrop-filter:blur(16px) saturate(135%);
                -webkit-backdrop-filter:blur(16px) saturate(135%);border:1px solid rgba(255,255,255,.78);
                border-radius:18px;box-shadow:0 12px 32px rgba(20,83,45,.13);padding:1.2rem 1.3rem}
            @supports not ((backdrop-filter:blur(1px)) or (-webkit-backdrop-filter:blur(1px))){.niu-hub__card{background:rgba(255,255,255,.93)}}
            .niu-hub svg.niu-ico{width:1.1em;height:1.1em;flex:0 0 auto;vertical-align:-.16em}
            .niu-hub__head{display:flex;align-items:center;gap:.7rem;margin-bottom:.5rem}
            .niu-hub__num{display:flex;align-items:center;justify-content:center;width:1.9rem;height:1.9rem;flex:none;border-radius:9px;
                font-weight:800;font-size:.9rem;color:#fff;background:linear-gradient(150deg,var(--g700),var(--g900))}
            .niu-hub__t{font-size:1.04rem;font-weight:700;margin:0}
            .niu-hub__d{font-size:.86rem;color:var(--ink-soft);margin:.1rem 0 .85rem}
            .niu-hub__evlist{list-style:none;margin:0 0 .9rem;padding:0;display:grid;gap:.45rem}
            .niu-hub__evlist li{display:flex;align-items:flex-start;gap:.5rem;font-size:.86rem;color:var(--ink)}
            .niu-hub__evlist svg{color:var(--g600);margin-top:.12rem}
            .niu-hub__btn{display:inline-flex;align-items:center;gap:.45rem;border:0;cursor:pointer;text-decoration:none;
                padding:.6rem 1.1rem;border-radius:11px;font-size:.86rem;font-weight:700;color:#fff;
                background:linear-gradient(150deg,var(--g700),var(--g900));box-shadow:0 8px 18px rgba(20,83,45,.3);transition:transform .15s}
            .niu-hub__btn:hover{transform:translateY(-1px)}
            .niu-hub__btn:focus-visible{outline:none;box-shadow:0 0 0 3px rgba(34,197,94,.4)}
            /* Drill-down accordion */
            .niu-acc{border:1px solid var(--line);border-radius:12px;background:rgba(255,255,255,.55);overflow:hidden;margin-bottom:.85rem}
            .niu-acc>summary{cursor:pointer;list-style:none;padding:.7rem .9rem;font-weight:600;font-size:.9rem;color:var(--g800);
                display:flex;align-items:center;gap:.5rem}
            .niu-acc>summary::-webkit-details-marker{display:none}
            .niu-acc>summary .niu-acc__chev{margin-left:auto;transition:transform .2s}
            .niu-acc[open]>summary .niu-acc__chev{transform:rotate(90deg)}
            .niu-acc__body{padding:0 .9rem .6rem}
            .niu-ftable{width:100%;border-collapse:collapse;font-size:.82rem}
            .niu-ftable th{text-align:left;font-size:.68rem;text-transform:uppercase;letter-spacing:.04em;color:var(--g800);
                padding:.4rem .5rem;border-bottom:1px solid var(--line);white-space:nowrap}
            .niu-ftable td{padding:.4rem .5rem;border-bottom:1px solid rgba(21,128,61,.08);vertical-align:middle}
            .niu-ftable tr:last-child td{border-bottom:0}
            .niu-fbadge{font-size:.68rem;font-weight:700;padding:.1rem .45rem;border-radius:999px}
            .niu-fbadge--ok{color:var(--g800);background:var(--g100);border:1px solid var(--g200)}
            .niu-fbadge--muted{color:#475569;background:#f1f5f9;border:1px solid #e2e8f0}
            .niu-mono{font-family:ui-monospace,Menlo,Consolas,monospace}
            .dark .niu-hub{--ink:#e8f6ee;--ink-soft:#9cc4ad;--line:rgba(134,239,172,.18)}
            .dark .niu-hub__card,.dark .niu-acc{background:rgba(16,40,28,.55)}
            .dark .niu-hub::before{background:linear-gradient(180deg,#0b1f16,#0a1a13)}
        </style>

        {{-- 1. Downloaded taxpayer files --}}
        <section class="niu-hub__card">
            <div class="niu-hub__head">
                <span class="niu-hub__num">1</span>
                <h2 class="niu-hub__t">{{ __('app.audit.hub_files_title') }}</h2>
            </div>
            <p class="niu-hub__d">{{ __('app.audit.hub_files_desc') }}</p>

            <details class="niu-acc">
                <summary>
                    @svg('heroicon-o-folder-open', 'niu-ico', ['aria-hidden' => 'true'])
                    {{ __('app.audit.hub_files_reveal', ['count' => $this->downloadedFiles()->count()]) }}
                    @svg('heroicon-o-chevron-right', 'niu-ico niu-acc__chev', ['aria-hidden' => 'true'])
                </summary>
                <div class="niu-acc__body">
                    @if ($this->downloadedFiles()->isEmpty())
                        <p class="niu-hub__d" style="margin:.6rem 0">{{ __('app.audit.no_extraction') }}</p>
                    @else
                        <table class="niu-ftable">
                            <thead><tr>
                                <th>{{ __('app.audit.period') }}</th>
                                <th>{{ __('app.ui.status') }}</th>
                                <th>{{ __('app.audit.taxpayers') }}</th>
                                <th>{{ __('app.audit.downloaded_at') }}</th>
                            </tr></thead>
                            <tbody>
                            @foreach ($this->downloadedFiles() as $file)
                                <tr>
                                    <td class="niu-mono">{{ $file->period_label }}</td>
                                    <td><span class="niu-fbadge {{ in_array($file->status, ['completed', 'unchanged'], true) ? 'niu-fbadge--ok' : 'niu-fbadge--muted' }}">{{ __('app.audit.status_'.$file->status) }}</span></td>
                                    <td>{{ number_format($file->row_count) }}</td>
                                    <td>{{ $file->extracted_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </details>

            <a href="{{ $this->filesUrl() }}" class="niu-hub__btn">@svg('heroicon-o-arrow-down-tray', 'niu-ico', ['aria-hidden' => 'true']){{ __('app.audit.hub_open_files') }}</a>
        </section>

        {{-- 2. Third parties & verification evidences --}}
        <section class="niu-hub__card">
            <div class="niu-hub__head">
                <span class="niu-hub__num">2</span>
                <h2 class="niu-hub__t">{{ __('app.audit.hub_evidence_title') }}</h2>
            </div>
            <p class="niu-hub__d">{{ __('app.audit.hub_evidence_desc') }}</p>
            <ul class="niu-hub__evlist">
                <li>@svg('heroicon-o-check-badge', 'niu-ico', ['aria-hidden' => 'true']){{ __('app.audit.hub_ev_1') }}</li>
                <li>@svg('heroicon-o-document-check', 'niu-ico', ['aria-hidden' => 'true']){{ __('app.audit.hub_ev_2') }}</li>
                <li>@svg('heroicon-o-link', 'niu-ico', ['aria-hidden' => 'true']){{ __('app.audit.hub_ev_3') }}</li>
                <li>@svg('heroicon-o-qr-code', 'niu-ico', ['aria-hidden' => 'true']){{ __('app.audit.hub_ev_4') }}</li>
            </ul>
            <a href="{{ $this->evidencesUrl() }}" class="niu-hub__btn">@svg('heroicon-o-users', 'niu-ico', ['aria-hidden' => 'true']){{ __('app.audit.hub_open_evidence') }}</a>
        </section>

        {{-- 3. Audit journal (Journaux d'audit) --}}
        <section class="niu-hub__card">
            <div class="niu-hub__head">
                <span class="niu-hub__num">3</span>
                <h2 class="niu-hub__t">{{ __('app.nav.audit_journal') }}</h2>
            </div>
            <p class="niu-hub__d">{{ __('app.audit.hub_journal_desc') }}</p>
            <a href="{{ $this->journalUrl() }}" class="niu-hub__btn">@svg('heroicon-o-clipboard-document-list', 'niu-ico', ['aria-hidden' => 'true']){{ __('app.audit.hub_open_journal') }}</a>
        </section>
    </div>
</x-filament-panels::page>
