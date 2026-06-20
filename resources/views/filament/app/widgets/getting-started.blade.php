<x-filament-widgets::widget>
    <div class="niu-gs">
        <style>
            .niu-gs{color:#0f231a;font-family:inherit}
            .niu-gs__card{background:rgba(255,255,255,.95);border:1px solid rgba(21,128,61,.16);border-radius:16px;
                box-shadow:0 8px 26px rgba(20,83,45,.09);padding:1.35rem 1.45rem}
            .niu-gs__head{display:flex;align-items:center;gap:.85rem;margin-bottom:1rem}
            .niu-gs__badge{display:flex;align-items:center;justify-content:center;width:2.7rem;height:2.7rem;flex:none;
                border-radius:14px;color:#fff;background:linear-gradient(150deg,#15803d,#14532d);box-shadow:0 8px 20px rgba(20,83,45,.4)}
            .niu-gs__badge svg{width:1.5rem;height:1.5rem}
            .niu-gs__t{font-size:1.1rem;font-weight:700;color:#14532d;margin:0}
            .niu-gs__s{font-size:.88rem;color:#46685a;margin:.15rem 0 0}
            .niu-gs__steps{list-style:none;margin:0;padding:0;display:grid;gap:.7rem;grid-template-columns:repeat(auto-fit,minmax(15rem,1fr))}
            .niu-gs__step{display:flex;gap:.7rem;align-items:flex-start;padding:.85rem .95rem;border-radius:13px;
                background:#f0fdf4;border:1px solid rgba(21,128,61,.14)}
            .niu-gs__n{display:flex;align-items:center;justify-content:center;width:1.6rem;height:1.6rem;flex:none;border-radius:50%;
                background:#16a34a;color:#fff;font-size:.8rem;font-weight:700}
            .niu-gs__step b{display:block;font-size:.9rem;color:#166534;margin-bottom:.15rem}
            .niu-gs__step a{font-size:.82rem;color:#15803d;font-weight:600;text-decoration:none}
            .niu-gs__step a:hover{text-decoration:underline}
            .dark .niu-gs{color:#e8f6ee}
            .dark .niu-gs__card{background:rgba(11,28,20,.92);border-color:rgba(134,239,172,.14)}
            .dark .niu-gs__t{color:#bbf7d0}
            .dark .niu-gs__step{background:rgba(255,255,255,.05);border-color:rgba(134,239,172,.12)}
            @media (prefers-reduced-motion:reduce){.niu-gs *{transition:none!important}}
        </style>

        <div class="niu-gs__card">
            <div class="niu-gs__head">
                <span class="niu-gs__badge">@svg('heroicon-o-rocket-launch', '', ['aria-hidden' => 'true'])</span>
                <div>
                    <h2 class="niu-gs__t">{{ __('app.gs.title') }}</h2>
                    <p class="niu-gs__s">{{ __('app.gs.intro') }}</p>
                </div>
            </div>

            <ol class="niu-gs__steps">
                <li class="niu-gs__step">
                    <span class="niu-gs__n">1</span>
                    <span><b>{{ __('app.gs.step1') }}</b><a href="{{ \App\Filament\App\Pages\ComplianceEngine::getUrl() }}">{{ __('app.gs.step1_cta') }} →</a></span>
                </li>
                <li class="niu-gs__step">
                    <span class="niu-gs__n">2</span>
                    <span><b>{{ __('app.gs.step2') }}</b><a href="{{ \App\Filament\App\Pages\Settings::getUrl() }}">{{ __('app.gs.step2_cta') }} →</a></span>
                </li>
                @if (auth()->user()?->isAdmin())
                    <li class="niu-gs__step">
                        <span class="niu-gs__n">3</span>
                        <span><b>{{ __('app.gs.step3') }}</b><a href="{{ \App\Filament\App\Resources\Users\UserResource::getUrl() }}">{{ __('app.gs.step3_cta') }} →</a></span>
                    </li>
                @endif
            </ol>
        </div>
    </div>
</x-filament-widgets::widget>
