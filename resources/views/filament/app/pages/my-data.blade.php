<x-filament-panels::page>
    <div class="niu-my">
        <style>
            .niu-my{--g600:#16a34a;--g700:#15803d;--g800:#166534;--ink:#0f231a;--ink-soft:#46685a;--line:rgba(21,128,61,.16);color:var(--ink);line-height:1.55}
            .niu-my .glass{background:rgba(255,255,255,.66);backdrop-filter:blur(14px) saturate(135%);border:1px solid rgba(255,255,255,.78);
                border-radius:16px;box-shadow:0 10px 30px rgba(20,83,45,.1);padding:1.1rem 1.25rem}
            @supports not (backdrop-filter:blur(1px)){.niu-my .glass{background:rgba(255,255,255,.93)}}
            .niu-my__intro{font-size:.92rem;color:var(--ink-soft);margin:0 0 1rem}
            .niu-my__grid{display:grid;grid-template-columns:1fr;gap:.9rem;margin-bottom:1rem}
            @media (min-width:720px){.niu-my__grid{grid-template-columns:1fr 1fr}}
            .niu-my h3{font-size:1rem;font-weight:800;color:var(--g700);margin:0 0 .3rem}
            .niu-my p{font-size:.88rem;margin:0;color:var(--ink)}
            .niu-my__rights{margin:.5rem 0 0;padding-left:1.1rem}
            .niu-my__rights li{font-size:.85rem;color:var(--ink);margin:.2rem 0}
            .niu-my__notice{display:inline-flex;align-items:center;gap:.35rem;margin-top:.4rem;font-size:.9rem;font-weight:700;color:var(--g700);text-decoration:none}
            .dark .niu-my{--ink:#e8f6ee;--ink-soft:#9cc4ad}
            .dark .niu-my .glass{background:rgba(16,40,28,.55)}
            .dark .niu-my h3,.dark .niu-my__notice{color:#bbf7d0}
        </style>

        <p class="niu-my__intro">{{ __('dp.my.intro') }}</p>

        <div class="niu-my__grid">
            <section class="glass">
                <h3>{{ __('dp.my.export_title') }}</h3>
                <p>{{ __('dp.my.export_body') }}</p>
            </section>
            <section class="glass">
                <h3>{{ __('dp.my.request_title') }}</h3>
                <p>{{ __('dp.my.request_body') }}</p>
                <ul class="niu-my__rights">
                    <li>{{ __('dp.request.t_access') }}</li>
                    <li>{{ __('dp.request.t_rectification') }}</li>
                    <li>{{ __('dp.request.t_erasure') }}</li>
                    <li>{{ __('dp.request.t_objection') }}</li>
                    <li>{{ __('dp.request.t_portability') }}</li>
                </ul>
            </section>
        </div>

        <a class="niu-my__notice" href="{{ url('/privacy') }}" target="_blank" rel="noopener">
            @svg('heroicon-o-arrow-top-right-on-square', '', ['style' => 'width:1.1em;height:1.1em'])
            {{ __('dp.my.notice_link') }}
        </a>

        {{-- The full privacy notice, embedded so it's readable without leaving the app. --}}
        <div style="margin-top:1.4rem">
            @include('partials.privacy-notice')
        </div>
    </div>
</x-filament-panels::page>
