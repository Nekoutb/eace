<x-filament-panels::page>
    <div class="niu-guide">
        <style>
            .niu-guide{
                --g50:#f0fdf4;--g100:#dcfce7;--g200:#bbf7d0;--g300:#86efac;--g500:#22c55e;--g600:#16a34a;
                --g700:#15803d;--g800:#166534;--g900:#14532d;--ink:#0f231a;--ink-soft:#46685a;
                --line:rgba(21,128,61,.16);--grad:linear-gradient(150deg,var(--g700),var(--g900));
                position:relative;z-index:0;color:var(--ink);
                font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;line-height:1.55}
            .niu-guide::before{content:"";position:absolute;inset:-26px -18px;z-index:-1;border-radius:30px;pointer-events:none;
                background:radial-gradient(60% 70% at 12% -4%,rgba(134,239,172,.5),transparent 60%),linear-gradient(180deg,#f4fdf7,#ecfdf3)}
            .niu-guide .glass{background:rgba(255,255,255,.62);backdrop-filter:blur(16px) saturate(135%);
                -webkit-backdrop-filter:blur(16px) saturate(135%);border:1px solid rgba(255,255,255,.78);
                border-radius:20px;box-shadow:0 12px 36px rgba(20,83,45,.13)}
            @supports not ((backdrop-filter:blur(1px)) or (-webkit-backdrop-filter:blur(1px))){.niu-guide .glass{background:rgba(255,255,255,.92)}}
            .niu-guide svg.niu-ico{width:1.1em;height:1.1em;flex:0 0 auto;vertical-align:-.16em}
            .niu-guide .niu-ico--lg{width:1.7em;height:1.7em}
            .niu-guide__hero{display:flex;align-items:center;gap:1rem;padding:1.15rem 1.35rem;margin-bottom:1.1rem}
            .niu-guide__badge{display:flex;align-items:center;justify-content:center;width:3rem;height:3rem;flex:none;
                border-radius:16px;color:#fff;background:var(--grad);box-shadow:0 8px 20px rgba(20,83,45,.4)}
            .niu-guide__t{font-size:1.18rem;font-weight:700;margin:0}
            .niu-guide__s{font-size:.875rem;color:var(--ink-soft);margin:.15rem 0 0;max-width:48rem}
            .niu-guide__list{display:flex;flex-direction:column;gap:.9rem}
            .niu-guide__card{display:flex;gap:1rem;padding:1.1rem 1.25rem}
            .niu-guide__num{display:flex;align-items:center;justify-content:center;width:2.1rem;height:2.1rem;flex:none;
                border-radius:11px;font-weight:800;font-size:.95rem;color:#fff;background:var(--grad);box-shadow:0 6px 14px rgba(20,83,45,.3)}
            .niu-guide__h{display:flex;align-items:center;gap:.45rem;font-size:1rem;font-weight:700;color:var(--g800);margin:0 0 .25rem}
            .niu-guide__b{font-size:.9rem;color:var(--ink);margin:0}
            .niu-guide__out{display:flex;gap:.4rem;align-items:flex-start;margin-top:.55rem;padding:.55rem .7rem;border-radius:11px;
                background:var(--g100);border:1px solid var(--g200);font-size:.85rem;color:var(--g900)}
            .niu-guide__out b{font-weight:700;margin-right:.25rem}
            .dark .niu-guide{--ink:#e8f6ee;--ink-soft:#9cc4ad;--line:rgba(134,239,172,.18)}
            .dark .niu-guide .glass{background:rgba(16,40,28,.55)}
            .dark .niu-guide::before{background:linear-gradient(180deg,#0b1f16,#0a1a13)}
            .dark .niu-guide__h{color:var(--g300)}
            .dark .niu-guide__out{background:rgba(255,255,255,.05)}
        </style>

        <header class="glass niu-guide__hero">
            <span class="niu-guide__badge">@svg('heroicon-o-book-open', 'niu-ico niu-ico--lg', ['aria-hidden' => 'true'])</span>
            <div>
                <h2 class="niu-guide__t">{{ __('app.nav.user_guide') }}</h2>
                <p class="niu-guide__s">{{ __('app.guide.intro') }}</p>
            </div>
        </header>

        @php
            $steps = [
                ['icon' => 'heroicon-o-user-group', 'title' => __('app.guide.s1_title'), 'body' => __('app.guide.s1_body'), 'out' => null],
                ['icon' => 'heroicon-o-document-plus', 'title' => __('app.guide.s2_title'), 'body' => __('app.guide.s2_body'), 'out' => __('app.guide.s2_out')],
                ['icon' => 'heroicon-o-banknotes', 'title' => __('app.guide.s3_title'), 'body' => __('app.guide.s3_body'), 'out' => __('app.guide.s3_out')],
                ['icon' => 'heroicon-o-clipboard-document-check', 'title' => __('app.guide.s4_title'), 'body' => __('app.guide.s4_body'), 'out' => __('app.guide.s4_out')],
                ['icon' => 'heroicon-o-shield-check', 'title' => __('app.guide.s5_title'), 'body' => __('app.guide.s5_body'), 'out' => null],
                ['icon' => 'heroicon-o-building-office-2', 'title' => __('app.guide.s6_title'), 'body' => __('app.guide.s6_body'), 'out' => null],
                ['icon' => 'heroicon-o-chat-bubble-left-right', 'title' => __('app.guide.s7_title'), 'body' => __('app.guide.s7_body'), 'out' => null],
            ];
        @endphp

        <div class="niu-guide__list">
            @foreach ($steps as $i => $step)
                <section class="glass niu-guide__card">
                    <span class="niu-guide__num">{{ $i + 1 }}</span>
                    <div>
                        <h3 class="niu-guide__h">@svg($step['icon'], 'niu-ico', ['aria-hidden' => 'true']){{ $step['title'] }}</h3>
                        <p class="niu-guide__b">{{ $step['body'] }}</p>
                        @if ($step['out'])
                            <div class="niu-guide__out">@svg('heroicon-o-arrow-right-circle', 'niu-ico', ['aria-hidden' => 'true'])<span><b>{{ __('app.guide.expect_label') }}</b>{{ $step['out'] }}</span></div>
                        @endif
                    </div>
                </section>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
