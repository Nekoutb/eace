<x-filament-panels::page>
    @php
        $isRecording = $this->stage !== 'settlement';
        $canRecord = $this->canRecord();
        $canSettle = $this->canSettle();

        // Pass / fail / not-run presentation for a single test cell.
        $pill = function (?bool $passed): array {
            return match ($passed) {
                true => ['cls' => 'niu-pill niu-pill--pass', 'icon' => 'heroicon-m-check-circle', 'label' => __('app.ui.passed')],
                false => ['cls' => 'niu-pill niu-pill--fail', 'icon' => 'heroicon-m-x-circle', 'label' => __('app.ui.failed')],
                default => ['cls' => 'niu-pill niu-pill--idle', 'icon' => 'heroicon-m-minus', 'label' => __('app.ui.not_run')],
            };
        };
    @endphp

    <div class="niu-ce">
        <style>
            .niu-ce{
                --g50:#f0fdf4;--g100:#dcfce7;--g200:#bbf7d0;--g300:#86efac;--g400:#4ade80;
                --g500:#22c55e;--g600:#16a34a;--g700:#15803d;--g800:#166534;--g900:#14532d;
                --ink:#0f231a;--ink-soft:#46685a;--line:rgba(21,128,61,.16);
                --glass:rgba(255,255,255,.56);--glass2:rgba(255,255,255,.74);
                --glassBrd:rgba(255,255,255,.78);--glassSh:0 12px 36px rgba(20,83,45,.14);
                --ring:0 0 0 3px rgba(34,197,94,.4);
                --gradBrand:linear-gradient(150deg,var(--g700),var(--g900));
                --blur:16px;--r:20px;
                position:relative;z-index:0;color:var(--ink);
                font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;
                line-height:1.5;
            }
            .niu-ce::before{
                content:"";position:absolute;inset:-26px -18px;z-index:-1;border-radius:30px;pointer-events:none;
                background:
                    radial-gradient(58% 70% at 10% -4%,rgba(134,239,172,.60),transparent 60%),
                    radial-gradient(54% 64% at 98% 6%,rgba(74,222,128,.42),transparent 55%),
                    radial-gradient(90% 90% at 50% 116%,rgba(187,247,208,.58),transparent 62%),
                    linear-gradient(180deg,#f4fdf7 0%,#ecfdf3 100%);
            }
            .niu-ce .glass{
                background:var(--glass);
                backdrop-filter:blur(var(--blur)) saturate(135%);
                -webkit-backdrop-filter:blur(var(--blur)) saturate(135%);
                border:1px solid var(--glassBrd);border-radius:var(--r);box-shadow:var(--glassSh);
            }
            @supports not ((backdrop-filter:blur(1px)) or (-webkit-backdrop-filter:blur(1px))){
                .niu-ce .glass{background:rgba(255,255,255,.92)}
            }
            .niu-ce svg.niu-ico{width:1.1em;height:1.1em;flex:0 0 auto;display:inline-block;vertical-align:-.16em}
            .niu-ico--lg{width:1.7em!important;height:1.7em!important}
            .niu-ico--xl{width:2.6em!important;height:2.6em!important}
            .niu-mono{font-family:ui-monospace,"SFMono-Regular",Menlo,Consolas,monospace;letter-spacing:.02em}

            /* Hero */
            .niu-hero{display:flex;align-items:center;gap:1rem;padding:1.15rem 1.35rem;margin-bottom:1.1rem}
            .niu-hero__badge{display:flex;align-items:center;justify-content:center;width:3rem;height:3rem;flex:none;
                border-radius:16px;color:#fff;background:var(--gradBrand);box-shadow:0 8px 20px rgba(20,83,45,.4)}
            .niu-hero__t{font-size:1.18rem;font-weight:700;color:var(--ink);margin:0}
            .niu-hero__s{font-size:.875rem;color:var(--ink-soft);margin:.15rem 0 0}

            /* Segmented toggle (aria-pressed button group) */
            .niu-seg{display:inline-flex;padding:.32rem;gap:.3rem;border-radius:16px;
                background:var(--glass2);border:1px solid var(--glassBrd);box-shadow:var(--glassSh);
                backdrop-filter:blur(var(--blur));-webkit-backdrop-filter:blur(var(--blur))}
            .niu-seg__btn{display:inline-flex;align-items:center;gap:.5rem;border:0;cursor:pointer;
                padding:.62rem 1.1rem;border-radius:12px;font-size:.9rem;font-weight:600;color:var(--ink-soft);
                background:transparent;transition:all .18s ease}
            .niu-seg__btn:hover{color:var(--g800);background:rgba(255,255,255,.5)}
            .niu-seg__btn:focus-visible{outline:none;box-shadow:var(--ring)}
            .niu-seg__btn[aria-pressed="true"]{color:#fff;background:var(--gradBrand);box-shadow:0 6px 16px rgba(20,83,45,.34)}
            .niu-seg__btn[aria-pressed="true"]:focus-visible{box-shadow:var(--ring),0 6px 16px rgba(20,83,45,.34)}
            .niu-seg__hint{font-size:.85rem;color:var(--ink-soft);margin:.6rem .15rem 1.1rem}

            /* Form */
            .niu-form{padding:1.35rem}
            .niu-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}
            .niu-field{display:flex;flex-direction:column;gap:.4rem}
            .niu-field--full{grid-column:1/-1}
            .niu-label{font-size:.82rem;font-weight:600;color:var(--g800)}
            .niu-input{width:100%;padding:.62rem .8rem;border-radius:12px;font-size:.92rem;color:var(--ink);
                background:rgba(255,255,255,.7);border:1px solid var(--line);outline:none;transition:border .15s,box-shadow .15s}
            .niu-input:focus,.niu-input:focus-visible{border-color:var(--g500);box-shadow:0 0 0 3px rgba(34,197,94,.25)}
            .niu-input--mono{font-family:ui-monospace,"SFMono-Regular",Menlo,Consolas,monospace;letter-spacing:.04em}
            textarea.niu-input{resize:vertical;min-height:8.5rem}
            .niu-help{font-size:.76rem;color:var(--ink-soft);margin:0}
            .niu-err{font-size:.76rem;color:#b91c1c;font-weight:600;margin:.1rem 0 0}

            /* Dropzone (file inputs) */
            .niu-drop{display:flex;align-items:center;gap:.85rem;padding:.9rem 1rem;border-radius:14px;cursor:pointer;
                background:rgba(255,255,255,.5);border:1.5px dashed var(--g300);transition:all .18s ease}
            .niu-drop:hover{border-color:var(--g500);background:rgba(220,252,231,.6)}
            .niu-drop:focus-within{border-color:var(--g500);background:rgba(220,252,231,.6);box-shadow:0 0 0 3px rgba(34,197,94,.25)}
            .niu-drop__icon{display:flex;align-items:center;justify-content:center;width:2.5rem;height:2.5rem;flex:none;
                border-radius:11px;color:var(--g700);background:var(--g100)}
            .niu-drop__title{display:block;font-size:.84rem;font-weight:600;color:var(--g800)}
            .niu-drop__hint{display:block;font-size:.76rem;color:var(--ink-soft);overflow-wrap:anywhere}
            .niu-file{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0 0 0 0);border:0}

            /* Scan extract panel */
            .niu-extract{padding:.9rem 1rem;border-radius:14px;background:rgba(220,252,231,.5);border:1px solid var(--g200)}
            .niu-extract__h{display:flex;align-items:center;gap:.4rem;font-size:.82rem;font-weight:700;color:var(--g800);margin-bottom:.6rem}
            .niu-extract__grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.5rem}
            .niu-extract__cell{padding:.4rem .55rem;border-radius:9px;background:rgba(255,255,255,.7);border:1px solid var(--line)}
            .niu-extract__k{display:block;font-size:.66rem;color:var(--ink-soft);text-transform:uppercase;letter-spacing:.03em}
            .niu-extract__v{font-size:.82rem;font-weight:600;color:var(--ink);overflow-wrap:anywhere}
            @media (max-width:640px){.niu-extract__grid{grid-template-columns:1fr 1fr}}

            /* Switch */
            .niu-switch{display:inline-flex;align-items:center;gap:.6rem;cursor:pointer;user-select:none}
            .niu-switch input{position:absolute;opacity:0;width:0;height:0}
            .niu-switch__track{position:relative;width:2.65rem;height:1.5rem;border-radius:999px;flex:none;
                background:rgba(21,128,61,.32);transition:background .18s ease}
            .niu-switch__thumb{position:absolute;top:.18rem;left:.18rem;width:1.14rem;height:1.14rem;border-radius:50%;
                background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.3);transition:transform .18s ease}
            .niu-switch input:checked + .niu-switch__track{background:linear-gradient(150deg,var(--g600),var(--g800))}
            .niu-switch input:checked + .niu-switch__track .niu-switch__thumb{transform:translateX(1.15rem)}
            .niu-switch input:focus-visible + .niu-switch__track{box-shadow:var(--ring)}
            .niu-switch__label{font-size:.86rem;font-weight:600;color:var(--g800)}

            /* Run button */
            .niu-form__actions{margin-top:1.25rem;display:flex;justify-content:flex-end}
            .niu-btn{position:relative;border:0;cursor:pointer;padding:.72rem 1.5rem;border-radius:13px;
                font-size:.92rem;font-weight:700;color:#fff;background:var(--gradBrand);
                box-shadow:0 10px 22px rgba(20,83,45,.34);transition:transform .15s ease,box-shadow .15s ease}
            .niu-btn:hover{transform:translateY(-1px);box-shadow:0 14px 28px rgba(20,83,45,.4)}
            .niu-btn:focus-visible{outline:none;box-shadow:var(--ring),0 10px 22px rgba(20,83,45,.34)}
            .niu-btn:active{transform:translateY(0)}
            .niu-btn:disabled{opacity:.7;cursor:progress}
            .niu-btn__c{display:inline-flex;align-items:center;gap:.55rem}
            .niu-spin{width:1rem;height:1rem;border-radius:50%;border:2px solid rgba(255,255,255,.45);
                border-top-color:#fff;animation:niu-rot .7s linear infinite}
            @keyframes niu-rot{to{transform:rotate(360deg)}}

            /* Results */
            .niu-result,.niu-disc{padding:1.35rem;margin-top:1.15rem}
            .niu-result__head{display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:1rem}
            .niu-result__title{display:inline-flex;align-items:center;gap:.55rem;font-size:1.02rem;font-weight:700;color:var(--ink)}
            .niu-chip{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--g800);
                background:var(--g100);border:1px solid var(--g200);padding:.18rem .55rem;border-radius:999px}
            .niu-link{display:inline-flex;align-items:center;gap:.35rem;font-size:.83rem;font-weight:600;color:var(--g700);text-decoration:none;border-radius:8px}
            .niu-link:hover{color:var(--g800);text-decoration:underline}
            .niu-link:focus-visible{outline:none;box-shadow:var(--ring)}

            .niu-headline{display:flex;flex-wrap:wrap;gap:1rem;align-items:stretch;margin-bottom:1.25rem}
            .niu-verdict{display:flex;align-items:center;gap:.8rem;padding:.85rem 1.1rem;border-radius:15px;flex:1 1 16rem;color:#fff}
            .niu-verdict--ok{background:var(--gradBrand)}
            .niu-verdict--no{background:linear-gradient(150deg,#b91c1c,#7f1d1d)}
            .niu-verdict__k{display:block;font-size:.74rem;text-transform:uppercase;letter-spacing:.05em}
            .niu-verdict__v{display:block;font-size:1.18rem;font-weight:800}
            .niu-metrics{display:flex;flex-wrap:wrap;gap:.65rem;flex:2 1 20rem}
            .niu-metric{flex:1 1 8rem;padding:.7rem .85rem;border-radius:13px;background:rgba(255,255,255,.62);border:1px solid var(--line)}
            .niu-metric__k{display:block;font-size:.72rem;color:var(--ink-soft);text-transform:uppercase;letter-spacing:.04em;margin-bottom:.3rem}
            .niu-metric__v{font-size:1.02rem;font-weight:700;color:var(--ink)}
            .niu-metric__v small{font-size:.7rem;font-weight:600;color:var(--ink-soft)}

            /* Tables */
            .niu-tablewrap{overflow-x:auto;border-radius:14px;border:1px solid var(--line);background:rgba(255,255,255,.45)}
            .niu-table{width:100%;border-collapse:collapse;font-size:.86rem}
            .niu-table th{text-align:left;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;
                color:var(--g800);background:rgba(220,252,231,.7);padding:.6rem .85rem;white-space:nowrap;border-bottom:1px solid var(--line)}
            .niu-table td{padding:.62rem .85rem;border-bottom:1px solid rgba(21,128,61,.08);vertical-align:middle}
            .niu-table tr:last-child td{border-bottom:0}
            .niu-table tbody tr:hover{background:rgba(240,253,244,.7)}
            .niu-ta-c{text-align:center}
            .niu-td-strong{font-weight:600;color:var(--ink)}
            .niu-td-muted{color:var(--ink-soft);font-size:.83rem}
            .niu-tag{display:inline-block;font-size:.72rem;font-weight:600;color:var(--ink-soft);
                background:rgba(255,255,255,.7);border:1px solid var(--line);padding:.12rem .5rem;border-radius:7px;white-space:nowrap}
            .niu-subh{display:flex;align-items:center;gap:.5rem;font-size:.95rem;font-weight:700;color:var(--g800);margin:1.3rem 0 .7rem}

            /* Pills */
            .niu-pill{display:inline-flex;align-items:center;gap:.3rem;font-size:.76rem;font-weight:700;
                padding:.2rem .55rem;border-radius:999px;white-space:nowrap}
            .niu-pill--pass{color:var(--g800);background:var(--g100);border:1px solid var(--g200)}
            .niu-pill--fail{color:#991b1b;background:#fee2e2;border:1px solid #fecaca}
            .niu-pill--idle{color:#475569;background:#f1f5f9;border:1px solid #e2e8f0}
            .niu-pill--icon{padding:.28rem;border-radius:50%}
            .niu-pill--icon > span{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}

            /* Badges */
            .niu-badge{display:inline-flex;align-items:center;gap:.3rem;font-size:.8rem;font-weight:700;
                padding:.22rem .6rem;border-radius:999px}
            .niu-badge--success{color:var(--g800);background:var(--g100);border:1px solid var(--g200)}
            .niu-badge--warning{color:#92400e;background:#fef3c7;border:1px solid #fde68a}
            .niu-badge--suspended{color:#9a3412;background:#ffedd5;border:1px solid #fed7aa}
            .niu-badge--danger{color:#991b1b;background:#fee2e2;border:1px solid #fecaca}
            .niu-badge--info{color:#115e59;background:#ccfbf1;border:1px solid #99f6e4}
            .niu-badge--gray{color:#475569;background:#f1f5f9;border:1px solid #e2e8f0}
            .niu-badge--primary{color:var(--g800);background:var(--g100);border:1px solid var(--g200)}

            /* Summary strip (settlement) */
            .niu-sum{display:flex;flex-wrap:wrap;gap:.65rem;margin-bottom:1.1rem}
            .niu-sum__c{flex:1 1 7rem;padding:.75rem .9rem;border-radius:13px;background:rgba(255,255,255,.62);border:1px solid var(--line)}
            .niu-sum__k{display:block;font-size:.72rem;color:var(--ink-soft);text-transform:uppercase;letter-spacing:.04em}
            .niu-sum__v{font-size:1.3rem;font-weight:800;color:var(--ink)}
            .niu-sum__c--ok .niu-sum__v{color:var(--g700)}
            .niu-sum__c--no .niu-sum__v{color:#b91c1c}

            /* Discrepancies */
            .niu-disc__list{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:.6rem}
            .niu-disc__item{display:flex;gap:.7rem;padding:.8rem .95rem;border-radius:13px;background:rgba(255,255,255,.6);border:1px solid var(--line)}
            .niu-disc__item--danger{border-left:4px solid #dc2626}
            .niu-disc__item--warning{border-left:4px solid #b45309}
            .niu-disc__dot{flex:none;color:#b91c1c}
            .niu-disc__item--warning .niu-disc__dot{color:#b45309}
            .niu-disc__t{display:flex;flex-wrap:wrap;align-items:center;gap:.45rem;font-size:.9rem;color:var(--ink)}
            .niu-disc__niu{font-size:.78rem;font-weight:700;color:var(--g800);background:var(--g100);padding:.08rem .4rem;border-radius:6px}
            .niu-disc__d{margin:.25rem 0 0;font-size:.83rem;color:var(--ink-soft)}
            .niu-allgood{display:flex;align-items:center;gap:.6rem;padding:.95rem 1.1rem;border-radius:13px;font-weight:600;
                color:var(--g800);background:var(--g100);border:1px solid var(--g200)}
            .niu-disc__lbl{font-weight:700;text-transform:uppercase;font-size:.66rem;letter-spacing:.03em;opacity:.75;margin-right:.15rem}
            .niu-disc__act{display:flex;align-items:flex-start;gap:.35rem;color:var(--g800);font-weight:600;margin-top:.25rem}

            /* Conformity matrix + draft email */
            .niu-matrix th{min-width:88px}
            .niu-email{padding:1rem;border-radius:14px;background:rgba(255,255,255,.55);border:1px solid var(--line)}
            .niu-email__row{display:flex;gap:.5rem;align-items:baseline;margin-bottom:.5rem;flex-wrap:wrap}
            .niu-email__k{font-size:.68rem;font-weight:700;text-transform:uppercase;color:var(--ink-soft);letter-spacing:.04em}
            .niu-email__subject{font-weight:700;color:var(--ink)}
            .niu-email__body{width:100%;border-radius:11px;border:1px solid var(--line);background:rgba(255,255,255,.85);
                padding:.7rem .85rem;font-size:.85rem;line-height:1.5;color:var(--ink);font-family:inherit;resize:vertical}
            .niu-email__body:focus{outline:none;border-color:var(--g500);box-shadow:0 0 0 3px rgba(34,197,94,.2)}
            .niu-email__actions{display:flex;justify-content:flex-end;margin-top:.55rem}
            .niu-btn--sm{padding:.5rem 1.05rem;font-size:.84rem;box-shadow:0 6px 14px rgba(20,83,45,.28)}

            /* Empty state */
            .niu-empty{display:flex;flex-direction:column;align-items:center;text-align:center;gap:.5rem;
                padding:2.6rem 1.5rem;margin-top:1.15rem;border-radius:var(--r);color:var(--ink-soft);
                border:1.5px dashed var(--g300);background:rgba(255,255,255,.4)}
            .niu-empty svg{color:var(--g500)}
            .niu-empty h3{font-size:1rem;font-weight:700;color:var(--g800);margin:.3rem 0 0}
            .niu-empty p{font-size:.86rem;max-width:30rem;margin:0}

            @media (max-width:640px){
                .niu-grid{grid-template-columns:1fr}
                .niu-seg{width:100%}
                .niu-seg__btn{flex:1;justify-content:center}
            }
            @media (prefers-reduced-motion:reduce){
                .niu-ce *{transition:none!important;animation:none!important}
            }

            /* Dark mode — keep the light-green character on a deep-green glass */
            .dark .niu-ce{--ink:#e8f6ee;--ink-soft:#9cc4ad;--line:rgba(134,239,172,.18);
                --glass:rgba(16,40,28,.5);--glass2:rgba(16,40,28,.62);--glassBrd:rgba(134,239,172,.16)}
            .dark .niu-ce::before{background:
                radial-gradient(58% 70% at 10% -4%,rgba(22,101,52,.5),transparent 60%),
                radial-gradient(54% 64% at 98% 6%,rgba(21,128,61,.4),transparent 55%),
                linear-gradient(180deg,#0b1f16 0%,#0a1a13 100%)}
            .dark .niu-label,.dark .niu-switch__label,.dark .niu-drop__title,.dark .niu-subh{color:var(--g300)}
            .dark .niu-input{background:rgba(255,255,255,.06);color:var(--ink)}
            .dark .niu-table th{background:rgba(22,101,52,.4);color:var(--g200)}
            .dark .niu-metric,.dark .niu-sum__c,.dark .niu-disc__item,.dark .niu-tag{background:rgba(255,255,255,.05)}
        </style>

        {{-- Hero --}}
        <header class="glass niu-hero">
            <span class="niu-hero__badge">@svg('heroicon-o-shield-check', 'niu-ico niu-ico--lg', ['aria-hidden' => 'true'])</span>
            <div>
                <h2 class="niu-hero__t">{{ __('app.nav.compliance_engine') }}</h2>
                <p class="niu-hero__s">{{ __('app.ui.engine_intro') }}</p>
            </div>
        </header>

        {{-- Live phase toggle (single-select button group) — only when the user's
             role covers both stages; single-stage roles open straight on theirs. --}}
        @if ($canRecord && $canSettle)
            <div class="niu-seg" role="group" aria-label="{{ __('app.ui.mode_label') }}">
                <button type="button" aria-pressed="{{ $isRecording ? 'true' : 'false' }}"
                        wire:click="setStage('recording')" @class(['niu-seg__btn', 'is-active' => $isRecording])>
                    @svg('heroicon-o-document-plus', 'niu-ico', ['aria-hidden' => 'true']){{ __('app.stage.recording') }}
                </button>
                <button type="button" aria-pressed="{{ ! $isRecording ? 'true' : 'false' }}"
                        wire:click="setStage('settlement')" @class(['niu-seg__btn', 'is-active' => ! $isRecording])>
                    @svg('heroicon-o-queue-list', 'niu-ico', ['aria-hidden' => 'true']){{ __('app.stage.settlement') }}
                </button>
            </div>
        @endif
        <p class="niu-seg__hint">{{ $isRecording ? __('app.ui.phase_recording_desc') : __('app.ui.phase_settlement_desc') }}</p>

        {{-- Inline form --}}
        <form wire:submit="run" class="glass niu-form">
            @if ($isRecording)
                <div class="niu-grid">
                    <label class="niu-drop niu-field--full">
                        <input type="file" wire:model="acfUpload" accept="application/pdf" class="niu-file">
                        <span class="niu-drop__icon">@svg('heroicon-o-document-arrow-up', 'niu-ico', ['aria-hidden' => 'true'])</span>
                        <span>
                            <span class="niu-drop__title">{{ __('app.ui.acf_pdf') }}</span>
                            <span class="niu-drop__hint" wire:loading.remove wire:target="acfUpload">{{ $acfUpload ? $acfUpload->getClientOriginalName() : __('app.ui.acf_help') }}</span>
                            <span class="niu-drop__hint" wire:loading wire:target="acfUpload">{{ __('app.ui.uploading') }}</span>
                        </span>
                    </label>
                    @error('acfUpload') <p class="niu-err niu-field--full">{{ $message }}</p> @enderror

                    <label class="niu-drop niu-field--full">
                        <input type="file" wire:model="invoiceUpload" accept="application/pdf,image/jpeg,image/png" class="niu-file">
                        <span class="niu-drop__icon">@svg('heroicon-o-document-magnifying-glass', 'niu-ico', ['aria-hidden' => 'true'])</span>
                        <span>
                            <span class="niu-drop__title">{{ __('app.ui.invoice_file') }}</span>
                            <span class="niu-drop__hint" wire:loading.remove wire:target="invoiceUpload">{{ $invoiceUpload ? $invoiceUpload->getClientOriginalName() : __('app.ui.invoice_file_help') }}</span>
                            <span class="niu-drop__hint" wire:loading wire:target="invoiceUpload">{{ __('app.ui.uploading') }}</span>
                        </span>
                    </label>
                    @error('invoiceUpload') <p class="niu-err niu-field--full">{{ $message }}</p> @enderror

                    @if ($extracted)
                        <div class="niu-extract niu-field--full">
                            <div class="niu-extract__h">@svg('heroicon-o-sparkles', 'niu-ico', ['aria-hidden' => 'true']){{ __('app.ui.scan_extract') }}</div>
                            <div class="niu-extract__grid">
                                @foreach ([
                                    'supplier_niu' => __('app.conformity.supplier_niu'),
                                    'supplier_name' => __('app.conformity.supplier_name'),
                                    'supplier_rccm' => __('app.conformity.supplier_rccm'),
                                    'client_niu' => __('app.conformity.client_niu'),
                                    'client_identity' => __('app.conformity.client_identity'),
                                    'client_rccm' => __('app.conformity.client_rccm'),
                                    'client_address' => __('app.conformity.client_address'),
                                    'date' => __('app.conformity.date'),
                                    'ht_amount' => __('app.conformity.ht'),
                                    'vat_amount' => __('app.conformity.vat'),
                                    'ttc_amount' => __('app.conformity.ttc'),
                                ] as $key => $label)
                                    <div class="niu-extract__cell">
                                        <span class="niu-extract__k">{{ $label }}</span>
                                        <span class="niu-extract__v niu-mono">{{ filled($extracted[$key] ?? null) ? $extracted[$key] : '—' }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="niu-field niu-field--full">
                        <label class="niu-label" for="niu-raw">{{ __('app.ui.niu_if_no_acf') }}</label>
                        <input id="niu-raw" type="text" wire:model="rawInput" placeholder="P058712345678X" autocomplete="off" class="niu-input niu-input--mono">
                        <p class="niu-help">{{ __('app.ui.niu_if_no_acf_help') }}</p>
                        @error('rawInput') <p class="niu-err">{{ $message }}</p> @enderror
                    </div>

                    <div class="niu-field">
                        <label class="niu-label" for="niu-amt">{{ __('app.ui.invoice_amount') }}</label>
                        <input id="niu-amt" type="number" min="0" wire:model="invoiceAmount" class="niu-input">
                        @error('invoiceAmount') <p class="niu-err">{{ $message }}</p> @enderror
                    </div>

                    <div class="niu-field">
                        <label class="niu-label" for="niu-name">{{ __('app.ui.invoice_name') }}</label>
                        <input id="niu-name" type="text" wire:model="invoiceName" class="niu-input">
                    </div>
                </div>
            @else
                <div class="niu-field niu-field--full">
                    <label class="niu-label" for="niu-batch">{{ __('app.ui.niu_batch') }}</label>
                    <textarea id="niu-batch" wire:model="nius" rows="7" class="niu-input niu-input--mono" placeholder="M058712345678X&#10;P112233445566Q&#10;…"></textarea>
                    <p class="niu-help">{{ __('app.ui.batch_help') }}</p>
                    @error('nius') <p class="niu-err">{{ $message }}</p> @enderror
                </div>
            @endif

            <div class="niu-form__actions">
                <button type="submit" class="niu-btn" wire:target="run" wire:loading.attr="disabled">
                    <span class="niu-btn__c" wire:loading.remove wire:target="run">@svg('heroicon-o-shield-check', 'niu-ico', ['aria-hidden' => 'true']){{ __('app.ui.run_checks') }}</span>
                    <span class="niu-btn__c" wire:loading.flex wire:target="run" style="display:none"><span class="niu-spin"></span>{{ __('app.ui.running') }}</span>
                </button>
            </div>
        </form>

        {{-- Recording result --}}
        @if ($result)
            <section class="glass niu-result">
                <div class="niu-result__head">
                    <span class="niu-result__title">@svg('heroicon-o-clipboard-document-check', 'niu-ico', ['aria-hidden' => 'true']){{ __('app.ui.results') }}<span class="niu-chip">{{ __('app.stage.recording') }}</span></span>
                    <a href="{{ $result['view_url'] }}" class="niu-link">@svg('heroicon-o-arrow-top-right-on-square', 'niu-ico', ['aria-hidden' => 'true']){{ __('app.ui.view_full_record') }}</a>
                </div>

                <div class="niu-headline">
                    <div class="niu-verdict {{ $result['conclusion']['payable'] ? 'niu-verdict--ok' : 'niu-verdict--no' }}">
                        @svg($result['conclusion']['payable'] ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle', 'niu-ico niu-ico--lg', ['aria-hidden' => 'true'])
                        <span>
                            <span class="niu-verdict__k">{{ __('app.ui.conclusion') }}</span>
                            <span class="niu-verdict__v">{{ $result['conclusion']['label'] }}</span>
                        </span>
                    </div>
                    <div class="niu-metrics">
                        <div class="niu-metric">
                            <span class="niu-metric__k">{{ __('app.ui.verdict') }}</span>
                            <span class="niu-badge niu-badge--{{ $result['verdict']['color'] }}">@svg($result['verdict']['icon'], 'niu-ico', ['aria-hidden' => 'true']){{ $result['verdict']['label'] }}</span>
                        </div>
                        <div class="niu-metric">
                            <span class="niu-metric__k">{{ __('app.ui.exposure_estimated') }}</span>
                            <span class="niu-metric__v">{{ number_format($result['exposure_xaf'], 0, ',', ' ') }} <small>FCFA</small></span>
                        </div>
                        <div class="niu-metric">
                            <span class="niu-metric__k">{{ __('app.ui.niu') }}</span>
                            <span class="niu-metric__v niu-mono">{{ $result['niu'] ?? $result['raw_input'] }}</span>
                        </div>
                    </div>
                </div>

                <div class="niu-tablewrap">
                    <table class="niu-table">
                        <thead><tr>
                            <th>{{ __('app.ui.check_col') }}</th>
                            <th>{{ __('app.ui.basis') }}</th>
                            <th class="niu-ta-c">{{ __('app.ui.result') }}</th>
                            <th>{{ __('app.ui.output') }}</th>
                        </tr></thead>
                        <tbody>
                        @foreach ($result['checks'] as $check)
                            @php($p = $pill($check['passed'] ?? null))
                            <tr>
                                <td class="niu-td-strong">{{ $check['label'] ?? '' }}</td>
                                <td><span class="niu-tag">{{ $check['article'] ?? '' }}</span></td>
                                <td class="niu-ta-c"><span class="{{ $p['cls'] }}">@svg($p['icon'], 'niu-ico', ['aria-hidden' => 'true'])<span>{{ $p['label'] }}</span></span></td>
                                <td class="niu-td-muted">{{ $check['detail'] ?? '' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                @if (! empty($result['invoice_checks']))
                    <h3 class="niu-subh">@svg('heroicon-o-receipt-percent', 'niu-ico', ['aria-hidden' => 'true']){{ __('app.ui.section_conformity_matrix') }}</h3>
                    <p class="niu-help" style="margin:-.35rem 0 .55rem">{{ __('app.ui.matrix_hint') }}</p>
                    <div class="niu-tablewrap">
                        <table class="niu-table niu-matrix">
                            <thead><tr>
                                <th>{{ __('app.ui.niu') }}</th>
                                @foreach ($result['invoice_checks'] as $finding)
                                    <th class="niu-ta-c" title="{{ __('app.conformity.type_'.($finding['type'] ?? 'forme')) }}">{{ $finding['label'] ?? '' }}</th>
                                @endforeach
                            </tr></thead>
                            <tbody><tr>
                                <td class="niu-td-strong niu-mono">{{ $result['niu'] ?? $result['raw_input'] }}</td>
                                @foreach ($result['invoice_checks'] as $finding)
                                    @php($p = $pill($finding['passed'] ?? null))
                                    <td class="niu-ta-c">
                                        <span class="{{ $p['cls'] }} niu-pill--icon" title="{{ ($finding['label'] ?? '').' — '.($finding['detail'] ?? $p['label']) }}" aria-label="{{ ($finding['label'] ?? '').': '.$p['label'] }}">@svg($p['icon'], 'niu-ico', ['aria-hidden' => 'true'])<span>{{ $p['label'] }}</span></span>
                                    </td>
                                @endforeach
                            </tr></tbody>
                        </table>
                    </div>
                @endif

                @if (! empty($result['vendor_email']))
                    <h3 class="niu-subh">@svg('heroicon-o-envelope', 'niu-ico', ['aria-hidden' => 'true']){{ __('app.ui.section_vendor_email') }}</h3>
                    <p class="niu-help" style="margin:-.35rem 0 .55rem">{{ __('app.ui.vendor_email_help') }}</p>
                    <div class="niu-email" x-data="{ copied: false }">
                        <div class="niu-email__row"><span class="niu-email__k">{{ __('app.ui.subject_label') }}</span><span class="niu-email__subject">{{ $result['vendor_email']['subject'] }}</span></div>
                        <textarea class="niu-email__body" readonly rows="9" x-ref="body">{{ $result['vendor_email']['subject'] }}

{{ $result['vendor_email']['body'] }}</textarea>
                        <div class="niu-email__actions">
                            <button type="button" class="niu-btn niu-btn--sm"
                                x-on:click="navigator.clipboard.writeText($refs.body.value); copied = true; setTimeout(() => copied = false, 1500)">
                                <span class="niu-btn__c">@svg('heroicon-o-clipboard-document', 'niu-ico', ['aria-hidden' => 'true'])<span x-text="copied ? @js(__('app.ui.copied')) : @js(__('app.ui.copy'))">{{ __('app.ui.copy') }}</span></span>
                            </button>
                        </div>
                    </div>
                @endif
            </section>
        @endif

        {{-- Settlement batch result --}}
        @if ($batch)
            <section class="glass niu-result">
                <div class="niu-result__head">
                    <span class="niu-result__title">@svg('heroicon-o-clipboard-document-check', 'niu-ico', ['aria-hidden' => 'true']){{ __('app.ui.results') }}<span class="niu-chip">{{ __('app.stage.settlement') }}</span></span>
                </div>

                <div class="niu-sum">
                    <div class="niu-sum__c"><span class="niu-sum__k">{{ __('app.ui.total') }}</span><span class="niu-sum__v">{{ $batch['summary']['total'] }}</span></div>
                    <div class="niu-sum__c niu-sum__c--ok"><span class="niu-sum__k">{{ __('app.ui.accepted') }}</span><span class="niu-sum__v">{{ $batch['summary']['accepted'] }}</span></div>
                    <div class="niu-sum__c niu-sum__c--no"><span class="niu-sum__k">{{ __('app.ui.rejected') }}</span><span class="niu-sum__v">{{ $batch['summary']['rejected'] }}</span></div>
                    <div class="niu-sum__c"><span class="niu-sum__k">{{ __('app.ui.exposure_estimated') }}</span><span class="niu-sum__v">{{ number_format($batch['summary']['exposure_xaf'], 0, ',', ' ') }} <small style="font-size:.6rem">FCFA</small></span></div>
                </div>

                <div class="niu-tablewrap">
                    <table class="niu-table">
                        <thead><tr>
                            <th>{{ __('app.ui.niu') }}</th>
                            @foreach ($this->checkColumns() as $col)
                                <th class="niu-ta-c">{{ $col['label'] }}</th>
                            @endforeach
                            <th>{{ __('app.ui.verdict') }}</th>
                            <th>{{ __('app.ui.decision_col') }}</th>
                        </tr></thead>
                        <tbody>
                        @foreach ($batch['rows'] as $row)
                            <tr>
                                <td class="niu-mono niu-td-strong">{{ $row['niu'] ?? $row['raw_input'] }}</td>
                                @foreach ($this->checkColumns() as $col)
                                    @php($cell = $row['checks_by_key'][$col['key']] ?? null)
                                    @php($p = $pill($cell['passed'] ?? null))
                                    <td class="niu-ta-c"><span class="{{ $p['cls'] }} niu-pill--icon" title="{{ $col['label'] }}: {{ $p['label'] }}">@svg($p['icon'], 'niu-ico', ['aria-hidden' => 'true'])<span>{{ $p['label'] }}</span></span></td>
                                @endforeach
                                <td><span class="niu-badge niu-badge--{{ $row['verdict']['color'] }}">@svg($row['verdict']['icon'], 'niu-ico', ['aria-hidden' => 'true']){{ $row['verdict']['label'] }}</span></td>
                                <td><span class="niu-badge niu-badge--{{ $row['conclusion']['payable'] ? 'success' : 'danger' }}">@svg($row['conclusion']['payable'] ? 'heroicon-m-check-circle' : 'heroicon-m-x-circle', 'niu-ico', ['aria-hidden' => 'true']){{ $row['conclusion']['decision'] }}</span></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        {{-- Comments — discrepancies & non-compliance --}}
        @if ($result || $batch)
            <section class="glass niu-disc">
                <h3 class="niu-subh" style="margin-top:0">@svg('heroicon-o-chat-bubble-bottom-center-text', 'niu-ico', ['aria-hidden' => 'true']){{ __('app.ui.section_remediation') }}</h3>
                @if (count($discrepancies))
                    <ul class="niu-disc__list">
                        @foreach ($discrepancies as $d)
                            <li class="niu-disc__item niu-disc__item--{{ $d['severity'] ?? 'danger' }}">
                                <span class="niu-disc__dot">@svg(($d['severity'] ?? 'danger') === 'warning' ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-x-circle', 'niu-ico', ['aria-hidden' => 'true'])</span>
                                <div>
                                    <div class="niu-disc__t">
                                        @isset($d['niu'])<span class="niu-disc__niu niu-mono">{{ $d['niu'] }}</span>@endisset
                                        <strong>{{ $d['label'] }}</strong>
                                        @if (! empty($d['article']))<span class="niu-tag">{{ $d['article'] }}</span>@endif
                                    </div>
                                    <p class="niu-disc__d"><span class="niu-disc__lbl">{{ __('app.ui.reason_col') }}:</span> {{ $d['detail'] }}</p>
                                    @if (! empty($d['action']))
                                        <p class="niu-disc__d niu-disc__act">@svg('heroicon-o-wrench-screwdriver', 'niu-ico', ['aria-hidden' => 'true'])<span><span class="niu-disc__lbl">{{ __('app.ui.action_col') }}:</span> {{ $d['action'] }}</span></p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="niu-allgood">@svg('heroicon-o-check-badge', 'niu-ico', ['aria-hidden' => 'true']){{ __('app.ui.no_discrepancies') }}</div>
                @endif
            </section>
        @endif

        {{-- Empty state --}}
        @if (! $result && ! $batch)
            <section class="niu-empty">
                @svg('heroicon-o-shield-check', 'niu-ico niu-ico--xl', ['aria-hidden' => 'true'])
                <h3>{{ __('app.ui.no_results_title') }}</h3>
                <p>{{ __('app.ui.no_results_body') }}</p>
            </section>
        @endif
    </div>
</x-filament-panels::page>
