@php($conversations = $this->recentConversations())
@php($awaitingReply = $conversations->contains(fn ($c) => $c->status === \App\Domain\Support\ConversationStatus::AwaitingCustomer))
<div class="nh-root" x-data="{ open: false, teaser: false }" x-init="setTimeout(() => { if (!open) teaser = true }, 4500)" wire:key="support-chat" wire:poll.10s>
    <style>
        .nh-root{position:fixed;right:1.1rem;bottom:1.1rem;z-index:99990;font-family:inherit}
        .nh-root *{box-sizing:border-box}
        .nh-l{position:relative;display:flex;align-items:center;justify-content:center;width:3.4rem;height:3.4rem;border:0;cursor:pointer;border-radius:50%;
            color:#fff;background:linear-gradient(150deg,#15803d,#14532d);box-shadow:0 12px 28px rgba(20,83,45,.4);transition:transform .15s}
        .nh-l:hover{transform:translateY(-2px)}
        .nh-l svg{width:1.6rem;height:1.6rem}
        .nh-dot{position:absolute;top:-2px;right:-2px;width:1.05rem;height:1.05rem;border-radius:50%;background:#ef4444;border:2px solid #fff;
            display:flex;align-items:center;justify-content:center;color:#fff;font-size:.62rem;font-weight:800;line-height:1;animation:nh-pulse 1.8s infinite}
        @keyframes nh-pulse{0%{box-shadow:0 0 0 0 rgba(239,68,68,.55)}70%{box-shadow:0 0 0 7px rgba(239,68,68,0)}100%{box-shadow:0 0 0 0 rgba(239,68,68,0)}}
        .nh-teaser{position:absolute;right:0;bottom:4.1rem;width:14rem;background:#fff;border:1px solid rgba(21,128,61,.18);border-radius:14px;
            padding:.7rem .85rem;font-size:.84rem;color:#0f231a;box-shadow:0 14px 34px rgba(20,83,45,.18);cursor:pointer}
        .nh-teaser b{color:#15803d}
        .nh-p{position:absolute;right:0;bottom:4.1rem;width:min(22rem,calc(100vw - 1.5rem));background:#fff;border-radius:18px;
            border:1px solid rgba(21,128,61,.16);box-shadow:0 22px 54px rgba(20,83,45,.24);overflow:hidden;display:flex;flex-direction:column}
        .nh-h{background:linear-gradient(150deg,#15803d,#14532d);color:#fff;padding:.95rem 1.1rem;display:flex;justify-content:space-between;align-items:flex-start}
        .nh-h h3{margin:0;font-size:1rem;font-weight:800}
        .nh-h p{margin:.1rem 0 0;font-size:.78rem;opacity:.9}
        .nh-x{background:transparent;border:0;color:#fff;cursor:pointer;font-size:1.2rem;line-height:1;opacity:.85}
        .nh-x:hover{opacity:1}
        .nh-body{padding:.85rem;max-height:min(58vh,24rem);overflow-y:auto;display:flex;flex-direction:column;gap:.65rem;background:#f8fdfb}
        .nh-intro{align-self:flex-start;background:#ecfdf3;border:1px solid #bbf7d0;color:#14532d;padding:.55rem .75rem;border-radius:12px;font-size:.85rem}
        .nh-c{display:flex;flex-direction:column;gap:.3rem;padding-bottom:.55rem;border-bottom:1px dashed rgba(21,128,61,.16)}
        .nh-c:last-child{border-bottom:0;padding-bottom:0}
        .nh-q{align-self:flex-end;background:#15803d;color:#fff;padding:.5rem .7rem;border-radius:13px 13px 3px 13px;font-size:.85rem;max-width:88%;white-space:pre-wrap;overflow-wrap:anywhere}
        .nh-r{align-self:flex-start;background:#fff;border:1px solid #bbf7d0;color:#14532d;padding:.5rem .7rem;border-radius:13px 13px 13px 3px;font-size:.85rem;max-width:88%;white-space:pre-wrap;overflow-wrap:anywhere}
        .nh-r b{display:block;font-size:.64rem;text-transform:uppercase;letter-spacing:.03em;color:#15803d;margin-bottom:.15rem}
        .nh-r.agent{background:#eff6ff;border-color:#bfdbfe;color:#1e3a5f}
        .nh-r.agent b{color:#2563eb}
        .nh-csat{align-self:flex-start;display:flex;align-items:center;gap:.4rem;font-size:.74rem;color:#46685a;margin-top:.1rem}
        .nh-csat button{cursor:pointer;border:1px solid rgba(21,128,61,.2);background:#fff;border-radius:8px;padding:.12rem .4rem;font-size:.95rem;line-height:1}
        .nh-form{padding:.7rem;border-top:1px solid rgba(21,128,61,.12);display:flex;flex-direction:column;gap:.35rem;background:#fff}
        .nh-form textarea{width:100%;padding:.55rem .7rem;border:1px solid rgba(21,128,61,.2);border-radius:10px;font-size:.86rem;font-family:inherit;outline:none;resize:none;min-height:2.6rem;color:#0f231a}
        .nh-form textarea:focus{border-color:#22c55e;box-shadow:0 0 0 3px rgba(34,197,94,.2)}
        .nh-err{font-size:.74rem;color:#b91c1c;font-weight:600;margin:0}
        .nh-hint{display:flex;align-items:center;gap:.35rem;font-size:.7rem;color:#6b8278;margin:0}
        .nh-hint kbd{background:#ecfdf3;border:1px solid #bbf7d0;border-radius:5px;padding:0 .3rem;font-size:.66rem;font-family:inherit;color:#15803d}
        .nh-hint .nh-sending{color:#15803d;font-weight:700}
        .dark .nh-p{background:#0b1c14;border-color:rgba(134,239,172,.16)}
        .dark .nh-body{background:rgba(255,255,255,.03)}
        .dark .nh-r,.dark .nh-form textarea{background:rgba(255,255,255,.06);color:#e8f6ee;border-color:rgba(134,239,172,.16)}
        .dark .nh-r.agent{background:rgba(37,99,235,.14);border-color:rgba(96,165,250,.3);color:#dbeafe}
        @media (prefers-reduced-motion:reduce){.nh-root *{transition:none!important;animation:none!important}}
    </style>

    {{-- Teaser --}}
    <div class="nh-teaser" x-show="teaser && !open" x-transition x-cloak @click="open = true; teaser = false; $wire.$refresh()">
        {{ __('app.chat.teaser') }} <b>{{ __('app.chat.teaser_cta') }}</b>
    </div>

    {{-- Panel --}}
    <div class="nh-p" x-show="open" x-transition x-cloak>
        <div class="nh-h">
            <div>
                <h3>{{ __('app.chat.title') }}</h3>
                <p>{{ __('app.chat.float_sub') }}</p>
            </div>
            <button type="button" class="nh-x" @click="open = false" aria-label="{{ __('app.chat.close') }}">&times;</button>
        </div>

        <div class="nh-body" x-ref="body">
            <div class="nh-intro">{{ __('app.chat.float_intro') }}</div>

            @foreach ($conversations as $c)
                <div class="nh-c" wire:key="conv-{{ $c->id }}">
                    @foreach ($c->messages->sortBy('id') as $m)
                        @if ($m->author === \App\Domain\Support\MessageAuthor::Customer)
                            <div class="nh-q">{{ $m->body }}</div>
                        @else
                            @php($isAgent = $m->author === \App\Domain\Support\MessageAuthor::Agent)
                            <div class="nh-r {{ $isAgent ? 'agent' : '' }}">
                                <b>{{ $isAgent ? __('app.chat.from_support') : __('app.chat.from_assistant') }}</b>{{ $m->body }}
                            </div>
                        @endif
                    @endforeach

                    @if ($c->status === \App\Domain\Support\ConversationStatus::Resolved && $c->csat_score === null)
                        <div class="nh-csat">
                            <span>{{ __('app.chat.csat_q') }}</span>
                            <button type="button" wire:click="rate({{ $c->id }}, 1)" aria-label="{{ __('app.chat.csat_up') }}">👍</button>
                            <button type="button" wire:click="rate({{ $c->id }}, 0)" aria-label="{{ __('app.chat.csat_down') }}">👎</button>
                        </div>
                    @elseif ($c->csat_score !== null)
                        <div class="nh-csat">{{ __('app.chat.csat_done') }} {{ $c->csat_score >= 1 ? '👍' : '👎' }}</div>
                    @endif
                </div>
            @endforeach
        </div>

        <form wire:submit="send" class="nh-form">
            <textarea wire:model="message" rows="2" placeholder="{{ __('app.chat.placeholder') }}" maxlength="2000"
                @keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); $el.form.requestSubmit() }"></textarea>
            @error('message') <p class="nh-err">{{ $message }}</p> @enderror
            <p class="nh-hint">
                <span wire:loading.remove wire:target="send"><kbd>{{ __('app.chat.enter_key') }}</kbd> {{ __('app.chat.enter_hint') }}</span>
                <span class="nh-sending" wire:loading wire:target="send">{{ __('app.chat.sending') }}</span>
            </p>
        </form>
    </div>

    {{-- Launcher --}}
    <button type="button" class="nh-l" @click="open = !open; teaser = false; if (open) $wire.$refresh()" aria-label="{{ __('app.chat.title') }}">
        @if ($awaitingReply)
            <span class="nh-dot" x-show="!open" x-cloak>1</span>
        @endif
        @svg('heroicon-o-chat-bubble-left-right')
    </button>
</div>
