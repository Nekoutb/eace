@php($ws = \App\Support\AccountSwitcher::workspacesFor(\Filament\Facades\Filament::auth()->user()))

{{-- Hidden while badged-in: impersonation is a read-only view of ONE workspace. --}}
@if (count($ws) > 1 && ! \App\Support\Impersonation::isActive())
    @php($current = collect($ws)->firstWhere('current', true))
    <div x-data="{ open: false }" style="position:relative" @keydown.escape="open=false">
        <style>
            .nws-btn{display:inline-flex;align-items:center;gap:.4rem;max-width:15rem;padding:.4rem .7rem;border-radius:10px;
                border:1px solid rgba(21,128,61,.22);background:#f0fdf4;color:#15803d;font-size:.82rem;font-weight:700;cursor:pointer}
            .nws-btn:hover{background:#dcfce7}
            .nws-btn svg{width:1rem;height:1rem;flex:0 0 auto}
            .nws-btn .nws-name{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
            .nws-menu{position:absolute;right:0;top:2.5rem;z-index:50;min-width:15rem;max-width:20rem;background:#fff;
                border:1px solid rgba(21,128,61,.18);border-radius:12px;box-shadow:0 14px 34px rgba(20,83,45,.18);overflow:hidden;padding:.3rem}
            .nws-head{font-size:.66rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6b8278;padding:.5rem .6rem .35rem}
            .nws-item{display:flex;align-items:center;gap:.45rem;width:100%;text-align:left;padding:.5rem .6rem;border:0;background:transparent;
                border-radius:8px;font-size:.85rem;font-weight:600;color:#0f231a;cursor:pointer}
            .nws-item:hover{background:#f0fdf4}
            .nws-item svg{width:1rem;height:1rem;flex:0 0 auto;color:#16a34a}
            .nws-current{color:#15803d;background:#f0fdf4;cursor:default}
            .nws-menu form{margin:0}
            .dark .nws-btn{background:rgba(22,101,52,.25);border-color:rgba(134,239,172,.25);color:#bbf7d0}
            .dark .nws-menu{background:#0f231a;border-color:rgba(134,239,172,.18)}
            .dark .nws-item{color:#e8f6ee}
            .dark .nws-item:hover,.dark .nws-current{background:rgba(22,101,52,.3)}
        </style>

        <button type="button" class="nws-btn" @click="open=!open" :aria-expanded="open" aria-haspopup="true">
            @svg('heroicon-m-building-office-2')
            <span class="nws-name">{{ $current['name'] ?? __('firm.switcher.workspace') }}</span>
            @svg('heroicon-m-chevron-up-down')
        </button>

        <div class="nws-menu" x-show="open" x-cloak x-transition @click.outside="open=false">
            <div class="nws-head">{{ __('firm.switcher.title') }}</div>
            @foreach ($ws as $w)
                @if ($w['current'])
                    <div class="nws-item nws-current">@svg('heroicon-m-check-circle'){{ $w['name'] }}</div>
                @else
                    <form method="POST" action="{{ route('workspace.switch', ['tenant' => $w['id']]) }}">
                        @csrf
                        {{-- Belt & braces: the target rides in the URL (primary) AND the body. --}}
                        <input type="hidden" name="tenant" value="{{ $w['id'] }}">
                        <button type="submit" class="nws-item">@svg('heroicon-m-arrow-right-circle'){{ $w['name'] }}</button>
                    </form>
                @endif
            @endforeach
        </div>
    </div>
@endif
