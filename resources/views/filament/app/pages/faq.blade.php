<x-filament-panels::page>
    @php
        $groups = $this->faqGroups();
        $corpus = '';
        foreach ($groups as $cat => $items) {
            $corpus .= ' '.$cat;
            foreach ($items as $it) {
                $corpus .= ' '.$it['q'].' '.$it['a'];
            }
        }
        $corpus = \Illuminate\Support\Str::lower($corpus);
    @endphp

    <div class="niu-faq" x-data="{ q: '' }">
        <style>
            .niu-faq{--g100:#dcfce7;--g200:#bbf7d0;--g300:#86efac;--g600:#16a34a;--g700:#15803d;--g800:#166534;--g900:#14532d;
                --ink:#0f231a;--ink-soft:#46685a;--line:rgba(21,128,61,.16);--grad:linear-gradient(150deg,var(--g700),var(--g900));
                position:relative;z-index:0;color:var(--ink);font-family:inherit;line-height:1.55}
            .niu-faq [x-cloak]{display:none!important}
            .niu-faq::before{content:"";position:absolute;inset:-26px -18px;z-index:-1;border-radius:30px;pointer-events:none;
                background:radial-gradient(60% 70% at 12% -4%,rgba(134,239,172,.5),transparent 60%),linear-gradient(180deg,#f4fdf7,#ecfdf3)}
            .niu-faq .glass{background:rgba(255,255,255,.66);backdrop-filter:blur(16px) saturate(135%);-webkit-backdrop-filter:blur(16px) saturate(135%);
                border:1px solid rgba(255,255,255,.78);border-radius:18px;box-shadow:0 12px 36px rgba(20,83,45,.12)}
            @supports not ((backdrop-filter:blur(1px)) or (-webkit-backdrop-filter:blur(1px))){.niu-faq .glass{background:rgba(255,255,255,.93)}}
            .niu-faq svg.niu-ico{width:1.1em;height:1.1em;flex:0 0 auto;vertical-align:-.16em}
            .niu-faq .niu-ico--lg{width:1.6em;height:1.6em}
            .niu-faq__hero{display:flex;align-items:center;gap:1rem;padding:1.1rem 1.3rem;margin-bottom:1rem}
            .niu-faq__badge{display:flex;align-items:center;justify-content:center;width:3rem;height:3rem;flex:none;border-radius:16px;
                color:#fff;background:var(--grad);box-shadow:0 8px 20px rgba(20,83,45,.4)}
            .niu-faq__t{font-size:1.18rem;font-weight:700;margin:0}
            .niu-faq__s{font-size:.875rem;color:var(--ink-soft);margin:.15rem 0 0;max-width:48rem}
            .niu-faq__searchwrap{display:flex;align-items:center;gap:.6rem;padding:.55rem .85rem;margin-bottom:1.1rem}
            .niu-faq__searchico{width:1.2rem;height:1.2rem;color:var(--g700);flex:none}
            .niu-faq__search{flex:1;border:0;background:transparent;outline:none;font-size:.95rem;font-family:inherit;color:var(--ink)}
            .niu-faq__clear{border:0;background:transparent;color:var(--ink-soft);cursor:pointer;font-size:1.3rem;line-height:1}
            .niu-faq__cat{padding:.4rem .5rem;margin-bottom:.9rem}
            .niu-faq__cath{font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:var(--g700);
                margin:0;padding:.5rem .75rem .35rem}
            .niu-faq__items{display:flex;flex-direction:column}
            .niu-faq__item{border-top:1px solid var(--line)}
            .niu-faq__item:first-child{border-top:0}
            .niu-faq__q{list-style:none;cursor:pointer;display:flex;align-items:center;gap:.6rem;padding:.7rem .75rem;
                font-size:.92rem;font-weight:600;color:var(--g900)}
            .niu-faq__q::-webkit-details-marker{display:none}
            .niu-faq__q::before{content:"";width:.5rem;height:.5rem;border-right:2px solid var(--g600);border-bottom:2px solid var(--g600);
                transform:rotate(-45deg);transition:transform .18s;flex:none;margin-left:.15rem}
            .niu-faq__item[open] .niu-faq__q::before{transform:rotate(45deg)}
            .niu-faq__item[open] .niu-faq__q{color:var(--g700)}
            .niu-faq__a{padding:0 .85rem .85rem 2.1rem;font-size:.88rem;color:var(--ink);margin:0}
            .niu-faq__none{padding:1rem 1.2rem;margin-bottom:1.1rem;font-size:.9rem;color:var(--ink-soft);text-align:center}
            .niu-faq__help{display:flex;align-items:center;gap:1rem;padding:1.1rem 1.3rem}
            .niu-faq__help .niu-faq__cath{padding:0;margin:0 0 .15rem}
            .dark .niu-faq{--ink:#e8f6ee;--ink-soft:#9cc4ad;--line:rgba(134,239,172,.16)}
            .dark .niu-faq .glass{background:rgba(16,40,28,.55)}
            .dark .niu-faq::before{background:linear-gradient(180deg,#0b1f16,#0a1a13)}
            .dark .niu-faq__cath,.dark .niu-faq__q{color:var(--g300)}
            @media (prefers-reduced-motion:reduce){.niu-faq *{transition:none!important}}
        </style>

        <header class="glass niu-faq__hero">
            <span class="niu-faq__badge">@svg('heroicon-o-question-mark-circle', 'niu-ico niu-ico--lg', ['aria-hidden' => 'true'])</span>
            <div>
                <h2 class="niu-faq__t">{{ __('app.nav.faq') }}</h2>
                <p class="niu-faq__s">{{ __('app.faq.intro') }}</p>
            </div>
        </header>

        <div class="glass niu-faq__searchwrap">
            @svg('heroicon-o-magnifying-glass', 'niu-faq__searchico', ['aria-hidden' => 'true'])
            <input type="search" class="niu-faq__search" x-model="q" placeholder="{{ __('app.faq.search') }}" aria-label="{{ __('app.faq.search') }}">
            <button type="button" class="niu-faq__clear" x-show="q !== ''" x-cloak @click="q = ''" aria-label="{{ __('app.chat.close') }}">&times;</button>
        </div>

        @foreach ($groups as $cat => $items)
            @php($catSearch = \Illuminate\Support\Str::lower($cat.' '.collect($items)->map(fn ($i) => $i['q'].' '.$i['a'])->implode(' ')))
            <section class="glass niu-faq__cat" data-s="{{ $catSearch }}" x-show="q === '' || $el.dataset.s.includes(q.toLowerCase())" x-cloak>
                <h3 class="niu-faq__cath">{{ $cat }}</h3>
                <div class="niu-faq__items">
                    @foreach ($items as $it)
                        @php($s = \Illuminate\Support\Str::lower($it['q'].' '.$it['a']))
                        <details class="niu-faq__item" data-s="{{ $s }}" x-show="q === '' || $el.dataset.s.includes(q.toLowerCase())" x-cloak>
                            <summary class="niu-faq__q">{{ $it['q'] }}</summary>
                            <p class="niu-faq__a">{{ $it['a'] }}</p>
                        </details>
                    @endforeach
                </div>
            </section>
        @endforeach

        <p class="glass niu-faq__none" x-show="q !== '' && ! {{ \Illuminate\Support\Js::from($corpus) }}.includes(q.toLowerCase())" x-cloak>
            {{ __('app.faq.none') }}
        </p>

        <footer class="glass niu-faq__help">
            <span class="niu-faq__badge">@svg('heroicon-o-chat-bubble-left-right', 'niu-ico niu-ico--lg', ['aria-hidden' => 'true'])</span>
            <div>
                <h3 class="niu-faq__cath">{{ __('app.faq.help_title') }}</h3>
                <p class="niu-faq__s">{{ __('app.faq.help_body') }}</p>
            </div>
        </footer>
    </div>
</x-filament-panels::page>
