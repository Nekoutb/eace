<x-filament-panels::page>
    <div class="niu-set">
        <style>
            .niu-set{--g100:#dcfce7;--g200:#bbf7d0;--g300:#86efac;--g500:#22c55e;--g600:#16a34a;
                --g700:#15803d;--g800:#166534;--g900:#14532d;--ink:#0f231a;--ink-soft:#46685a;
                --line:rgba(21,128,61,.16);--ring:0 0 0 3px rgba(34,197,94,.4);
                --grad:linear-gradient(150deg,var(--g700),var(--g900));
                position:relative;z-index:0;color:var(--ink);max-width:34rem;
                font-family:inherit}
            .niu-set::before{content:"";position:absolute;inset:-26px -18px;z-index:-1;border-radius:30px;pointer-events:none;
                background:radial-gradient(60% 70% at 12% -4%,rgba(134,239,172,.5),transparent 60%),linear-gradient(180deg,#f4fdf7,#ecfdf3)}
            .niu-set .glass{background:rgba(255,255,255,.62);backdrop-filter:blur(16px) saturate(135%);
                -webkit-backdrop-filter:blur(16px) saturate(135%);border:1px solid rgba(255,255,255,.78);
                border-radius:20px;box-shadow:0 12px 36px rgba(20,83,45,.14)}
            @supports not ((backdrop-filter:blur(1px)) or (-webkit-backdrop-filter:blur(1px))){.niu-set .glass{background:rgba(255,255,255,.92)}}
            .niu-set svg.niu-ico{width:1.1em;height:1.1em;flex:0 0 auto;vertical-align:-.16em}
            .niu-set .niu-ico--lg{width:1.7em;height:1.7em}
            .niu-set__hero{display:flex;align-items:center;gap:1rem;padding:1.15rem 1.35rem;margin-bottom:1.1rem}
            .niu-set__badge{display:flex;align-items:center;justify-content:center;width:3rem;height:3rem;flex:none;
                border-radius:16px;color:#fff;background:var(--grad);box-shadow:0 8px 20px rgba(20,83,45,.4)}
            .niu-set__t{font-size:1.18rem;font-weight:700;margin:0}
            .niu-set__s{font-size:.875rem;color:var(--ink-soft);margin:.15rem 0 0}
            .niu-set__form{padding:1.35rem;display:flex;flex-direction:column;gap:1.1rem}
            .niu-set__field{display:flex;flex-direction:column;gap:.4rem}
            .niu-set__label{font-size:.82rem;font-weight:600;color:var(--g800)}
            .niu-set__input{width:100%;padding:.62rem .8rem;border-radius:12px;font-size:.92rem;color:var(--ink);
                background:rgba(255,255,255,.7);border:1px solid var(--line);outline:none;transition:border .15s,box-shadow .15s}
            .niu-set__input:focus{border-color:var(--g500);box-shadow:0 0 0 3px rgba(34,197,94,.25)}
            .niu-set__err{font-size:.76rem;color:#b91c1c;font-weight:600;margin:.1rem 0 0}
            .niu-set__actions{display:flex;justify-content:flex-end}
            .niu-set__btn{border:0;cursor:pointer;padding:.72rem 1.5rem;border-radius:13px;font-size:.92rem;font-weight:700;
                color:#fff;background:var(--grad);box-shadow:0 10px 22px rgba(20,83,45,.34);transition:transform .15s,box-shadow .15s;
                display:inline-flex;align-items:center;gap:.5rem}
            .niu-set__btn:hover{transform:translateY(-1px)}
            .niu-set__btn:focus-visible{outline:none;box-shadow:var(--ring),0 10px 22px rgba(20,83,45,.34)}
            @media (prefers-reduced-motion:reduce){.niu-set *{transition:none!important}}
            .dark .niu-set{--ink:#e8f6ee;--ink-soft:#9cc4ad;--line:rgba(134,239,172,.18)}
            .dark .niu-set .glass{background:rgba(16,40,28,.55)}
            .dark .niu-set::before{background:linear-gradient(180deg,#0b1f16,#0a1a13)}
            .dark .niu-set__label{color:var(--g300)}
            .dark .niu-set__input{background:rgba(255,255,255,.06);color:var(--ink)}
        </style>

        <header class="glass niu-set__hero">
            <span class="niu-set__badge">@svg('heroicon-o-key', 'niu-ico niu-ico--lg', ['aria-hidden' => 'true'])</span>
            <div>
                <h2 class="niu-set__t">{{ __('app.password_change.title') }}</h2>
                <p class="niu-set__s">{{ __('app.password_change.intro') }}</p>
            </div>
        </header>

        <form wire:submit="save" class="glass niu-set__form">
            <div class="niu-set__field">
                <label class="niu-set__label" for="cp-new">{{ __('app.password_change.new') }}</label>
                <input id="cp-new" type="password" wire:model="password" class="niu-set__input" autocomplete="new-password" required>
                @error('password') <p class="niu-set__err">{{ $message }}</p> @enderror
            </div>

            <div class="niu-set__field">
                <label class="niu-set__label" for="cp-confirm">{{ __('app.password_change.confirm') }}</label>
                <input id="cp-confirm" type="password" wire:model="password_confirmation" class="niu-set__input" autocomplete="new-password" required>
            </div>

            <div class="niu-set__actions">
                <button type="submit" class="niu-set__btn">@svg('heroicon-o-check', 'niu-ico', ['aria-hidden' => 'true']){{ __('app.password_change.submit') }}</button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
