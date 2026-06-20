<x-filament-widgets::widget>
    <div class="niu-chat">
        <style>
            .niu-chat{
                --g100:#dcfce7;--g200:#bbf7d0;--g300:#86efac;--g500:#22c55e;--g600:#16a34a;
                --g700:#15803d;--g800:#166534;--g900:#14532d;--ink:#0f231a;--ink-soft:#46685a;
                --line:rgba(21,128,61,.16);--ring:0 0 0 3px rgba(34,197,94,.35);
                --grad:linear-gradient(150deg,var(--g700),var(--g900));
                position:relative;z-index:0;color:var(--ink);
                font-family:inherit}
            .niu-chat .glass{background:rgba(255,255,255,.9);backdrop-filter:blur(16px) saturate(135%);
                -webkit-backdrop-filter:blur(16px) saturate(135%);border:1px solid rgba(255,255,255,.78);
                border-radius:18px;box-shadow:0 12px 36px rgba(20,83,45,.12);padding:1.15rem 1.25rem}
            @supports not ((backdrop-filter:blur(1px)) or (-webkit-backdrop-filter:blur(1px))){.niu-chat .glass{background:rgba(255,255,255,.92)}}
            .niu-chat svg.niu-ico{width:1.1em;height:1.1em;flex:0 0 auto;vertical-align:-.16em}
            .niu-chat__head{display:flex;align-items:center;gap:.7rem;margin-bottom:.2rem}
            .niu-chat__badge{display:flex;align-items:center;justify-content:center;width:2.4rem;height:2.4rem;flex:none;
                border-radius:13px;color:#fff;background:var(--grad);box-shadow:0 6px 16px rgba(20,83,45,.34)}
            .niu-chat__t{font-size:1.02rem;font-weight:700;margin:0}
            .niu-chat__s{font-size:.82rem;color:var(--ink-soft);margin:.1rem 0 0;max-width:44rem}
            .niu-chat__form{margin-top:.9rem;display:flex;flex-direction:column;gap:.5rem}
            .niu-chat__ta{width:100%;padding:.65rem .8rem;border-radius:12px;font-size:.9rem;color:var(--ink);
                background:rgba(255,255,255,.72);border:1px solid var(--line);outline:none;resize:vertical;min-height:4.5rem;
                font-family:inherit;transition:border .15s,box-shadow .15s}
            .niu-chat__ta:focus{border-color:var(--g500);box-shadow:0 0 0 3px rgba(34,197,94,.22)}
            .niu-chat__err{font-size:.76rem;color:#b91c1c;font-weight:600;margin:0}
            .niu-chat__actions{display:flex;justify-content:flex-end}
            .niu-chat__btn{border:0;cursor:pointer;padding:.6rem 1.25rem;border-radius:12px;font-size:.88rem;font-weight:700;
                color:#fff;background:var(--grad);box-shadow:0 8px 18px rgba(20,83,45,.3);display:inline-flex;align-items:center;gap:.45rem;
                transition:transform .15s,box-shadow .15s}
            .niu-chat__btn:hover{transform:translateY(-1px)}
            .niu-chat__btn:focus-visible{outline:none;box-shadow:var(--ring),0 8px 18px rgba(20,83,45,.3)}
            .niu-chat__btn:disabled{opacity:.7;cursor:progress}
            .niu-chat__thread{margin-top:1rem;display:flex;flex-direction:column;gap:.7rem}
            .niu-chat__th{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--g800);margin:0}
            .niu-chat__msg{padding:.7rem .85rem;border-radius:13px;background:rgba(255,255,255,.6);border:1px solid var(--line)}
            .niu-chat__q{display:flex;gap:.5rem;align-items:flex-start;font-size:.88rem;color:var(--ink)}
            .niu-chat__meta{font-size:.7rem;color:var(--ink-soft);margin:.35rem 0 0}
            .niu-chat__reply{margin-top:.55rem;padding:.6rem .75rem;border-radius:11px;background:var(--g100);border:1px solid var(--g200)}
            .niu-chat__reply b{display:flex;align-items:center;gap:.35rem;font-size:.7rem;text-transform:uppercase;letter-spacing:.03em;color:var(--g800);margin-bottom:.2rem}
            .niu-chat__reply p{margin:0;font-size:.86rem;color:var(--ink)}
            .niu-chat__pill{font-size:.66rem;font-weight:700;text-transform:uppercase;letter-spacing:.03em;padding:.1rem .45rem;border-radius:999px;white-space:nowrap}
            .niu-chat__pill--new{color:#92400e;background:#fef3c7;border:1px solid #fde68a}
            .niu-chat__pill--answered{color:var(--g800);background:var(--g100);border:1px solid var(--g200)}
            .dark .niu-chat{--ink:#e8f6ee;--ink-soft:#9cc4ad;--line:rgba(134,239,172,.18)}
            .dark .niu-chat .glass{background:rgba(16,40,28,.55)}
            .dark .niu-chat__ta{background:rgba(255,255,255,.06);color:var(--ink)}
            .dark .niu-chat__msg{background:rgba(255,255,255,.05)}
            @media (prefers-reduced-motion:reduce){.niu-chat *{transition:none!important}}
        </style>

        <div class="glass">
            <div class="niu-chat__head">
                <span class="niu-chat__badge">@svg('heroicon-o-chat-bubble-left-right', 'niu-ico', ['aria-hidden' => 'true'])</span>
                <div>
                    <h2 class="niu-chat__t">{{ __('app.chat.title') }}</h2>
                    <p class="niu-chat__s">{{ __('app.chat.intro') }}</p>
                </div>
            </div>

            <form wire:submit="send" class="niu-chat__form">
                <textarea wire:model="message" rows="3" class="niu-chat__ta" placeholder="{{ __('app.chat.placeholder') }}"></textarea>
                @error('message') <p class="niu-chat__err">{{ $message }}</p> @enderror
                <div class="niu-chat__actions">
                    <button type="submit" class="niu-chat__btn" wire:target="send" wire:loading.attr="disabled">
                        @svg('heroicon-o-paper-airplane', 'niu-ico', ['aria-hidden' => 'true']){{ __('app.chat.send') }}
                    </button>
                </div>
            </form>

            @php($queries = $this->recentQueries())
            @if ($queries->isNotEmpty())
                <div class="niu-chat__thread">
                    <p class="niu-chat__th">{{ __('app.chat.your_questions') }}</p>
                    @foreach ($queries as $q)
                        <div class="niu-chat__msg">
                            <div class="niu-chat__q">
                                <span style="flex:1">{{ $q->message }}</span>
                                <span class="niu-chat__pill niu-chat__pill--{{ $q->isAnswered() ? 'answered' : 'new' }}">{{ $q->isAnswered() ? __('app.chat.status_answered') : __('app.chat.status_new') }}</span>
                            </div>
                            <p class="niu-chat__meta">{{ $q->created_at?->translatedFormat('d/m/Y H:i') }}</p>
                            @if (filled($q->response))
                                <div class="niu-chat__reply">
                                    <b>@svg('heroicon-o-lifebuoy', 'niu-ico', ['aria-hidden' => 'true']){{ __('app.chat.reply_label') }}</b>
                                    <p>{{ $q->response }}</p>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-filament-widgets::widget>
