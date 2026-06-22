<x-filament-panels::page>
    @php($ref = $this->authorityReference())
    <div class="niu-ropa">
        <style>
            .niu-ropa{--g100:#dcfce7;--g200:#bbf7d0;--g600:#16a34a;--g700:#15803d;--g800:#166534;--g900:#14532d;
                --ink:#0f231a;--ink-soft:#46685a;--line:rgba(21,128,61,.16);color:var(--ink);line-height:1.55}
            .niu-ropa .glass{background:rgba(255,255,255,.66);backdrop-filter:blur(14px) saturate(135%);
                border:1px solid rgba(255,255,255,.78);border-radius:16px;box-shadow:0 10px 30px rgba(20,83,45,.1);padding:1rem 1.2rem;margin-bottom:.9rem}
            @supports not (backdrop-filter:blur(1px)){.niu-ropa .glass{background:rgba(255,255,255,.93)}}
            .niu-ropa__head{font-size:.85rem;color:var(--ink-soft)}
            .niu-ropa__head b{color:var(--g800)}
            .niu-ropa__name{font-size:1rem;font-weight:800;color:var(--g700);margin:0 0 .6rem}
            .niu-ropa dl{display:grid;grid-template-columns:1fr;gap:.5rem;margin:0}
            @media (min-width:720px){.niu-ropa dl{grid-template-columns:1fr 1fr}}
            .niu-ropa dt{font-size:.66rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:var(--g600);margin-bottom:.1rem}
            .niu-ropa dd{margin:0 0 .4rem;font-size:.86rem;color:var(--ink)}
            .dark .niu-ropa{--ink:#e8f6ee;--ink-soft:#9cc4ad;--line:rgba(134,239,172,.16)}
            .dark .niu-ropa .glass{background:rgba(16,40,28,.55)}
            .dark .niu-ropa__name,.dark .niu-ropa dt{color:var(--g200)}
        </style>

        <div class="glass niu-ropa__head">
            {{ __('dp.ropa.intro') }}<br>
            <b>{{ __('dp.ropa.controller') }}:</b> {{ $this->controllerName() }} ·
            <b>{{ __('dp.ropa.authority_ref') }}:</b> {{ $ref !== '' ? $ref : __('dp.ropa.authority_pending') }}
        </div>

        @foreach ($this->activities() as $a)
            <section class="glass">
                <h3 class="niu-ropa__name">{{ $a['name'] }}</h3>
                <dl>
                    <div><dt>{{ __('dp.ropa.col_purpose') }}</dt><dd>{{ $a['purpose'] }}</dd></div>
                    <div><dt>{{ __('dp.ropa.col_basis') }}</dt><dd>{{ $a['basis'] }}</dd></div>
                    <div><dt>{{ __('dp.ropa.col_subjects') }}</dt><dd>{{ $a['subjects'] }}</dd></div>
                    <div><dt>{{ __('dp.ropa.col_data') }}</dt><dd>{{ $a['data'] }}</dd></div>
                    <div><dt>{{ __('dp.ropa.col_recipients') }}</dt><dd>{{ $a['recipients'] }}</dd></div>
                    <div><dt>{{ __('dp.ropa.col_transfers') }}</dt><dd>{{ $a['transfers'] }}</dd></div>
                    <div><dt>{{ __('dp.ropa.col_retention') }}</dt><dd>{{ $a['retention'] }}</dd></div>
                </dl>
            </section>
        @endforeach
    </div>
</x-filament-panels::page>
