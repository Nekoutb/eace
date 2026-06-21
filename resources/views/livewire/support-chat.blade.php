@php($conversations = $this->recentConversations())
<div class="nh-root" x-data="{ open: false, teaser: false }" x-init="setTimeout(() => { if (!open) teaser = true }, 4500)" wire:key="support-chat">
    <style>
        .nh-root{position:fixed;right:1.1rem;bottom:1.1rem;z-index:99990;font-family:inherit}
        .nh-root *{box-sizing:border-box}
        .nh-l{display:flex;align-items:center;justify-content:center;width:3.4rem;height:3.4rem;border:0;cursor:pointer;border-radius:50%;
            color:#fff;background:linear-gradient(150deg,#15803d,#14532d);box-shadow:0 12px 28px rgba(20,83,45,.4);transition:transform .15s}
        .nh-l:hover{transform:translateY(-2px)}
        .nh-l svg{width:1.6rem;height:1.6rem}
        .nh-teaser{position:absolute;right:0;bottom:4.1rem;width:14rem;background:#fff;border:1px solid rgba(21,128,61,.18);border-radius:14px;
            padding:.7rem .85rem;font-size:.84rem;color:#0f231a;box-shadow:0 14px 34px rgba(20,83,45,.18);cursor:pointer}
        .nh-teaser b{color:#15803d}
        .nh-p{position:absolute;right:0;bottom:4.1rem;width:21.5rem;max-width:calc(100vw - 2.2rem);background:#fff;border-radius:18px;
            border:1px solid rgba(21,128,61,.16);box-shadow:0 22px 54px rgba(20,83,45,.24);overflow:hidden;display:flex;flex-direction:column}
        .nh-h{background:linear-gradient(150deg,#15803d,#14532d);color:#fff;padding:.95rem 1.1rem;display:flex;justify-content:space-between;align-items:flex-start}
        .nh-h h3{margin:0;font-size:1rem;font-weight:800}
        .nh-h p{margin:.1rem 0 0;font-size:.78rem;opacity:.9}
        .nh-x{background:transparent;border:0;color:#fff;cursor:pointer;font-size:1.2rem;line-height:1;opacity:.85}
        .nh-x:hover{opacity:1}
        .nh-body{padding:.85rem;max-height:21rem;overflow:auto;display:flex;flex-direction:column;gap:.7rem;background:#f8fdfb}
        .nh-intro{align-self:flex-start;background:#ecfdf3;border:1px solid #bbf7d0;color:#14532d;padding:.55rem .75rem;border-radius:12px;font-size:.85rem}
        .nh-c{display:flex;flex-direction:column;gap:.35rem;padding-bottom:.6rem;border-bottom:1px dashed rgba(21,128,61,.16)}
        .nh-c:last-child{border-bottom:0}
        .nh-q{align-self:flex-end;background:#15803d;color:#fff;padding:.5rem .7rem;border-radius:12px;font-size:.85rem;max-width:90%;white-space:pre-wrap}
        .nh-r{align-self:flex-start;background:#fff;border:1px solid #bbf7d0;color:#14532d;padding:.5rem .7rem;border-radius:12px;font-size:.85rem;max-width:90%;white-space:pre-wrap}
        .nh-r b{display:block;font-size:.66rem;text-transform:uppercase;letter-spacing:.03em;color:#15803d;margin-bottom:.15rem}
        .nh-meta{align-self:flex-end;font-size:.66rem;color:#6b8278}
        .nh-csat{align-self:flex-start;display:flex;align-items:center;gap:.4rem;font-size:.74rem;color:#46685a}
        .nh-csat button{cursor:pointer;border:1px solid rgba(21,128,61,.2);background:#fff;border-radius:8px;padding:.12rem .4rem;font-size:.95rem;line-height:1}
        .nh-form{padding:.7rem;border-top:1px solid rgba(21,128,61,.12);display:flex;flex-direction:column;gap:.45rem;background:#fff}
        .nh-form textarea{width:100%;padding:.55rem .7rem;border:1px solid rgba(21,128,61,.2);border-radius:10px;font-size:.86rem;font-family:inherit;outline:none;resize:none;min-height:2.7rem;color:#0f231a}
        .nh-form textarea:focus{border-color:#22c55e;box-shadow:0 0 0 3px rgba(34,197,94,.2)}
        .nh-err{font-size:.74rem;color:#b91c1c;font-weight:600;margin:0}
        .nh-send{border:0;cursor:pointer;padding:.55rem;border-radius:10px;font-size:.88rem;font-weight:700;color:#fff;background:linear-gradient(150deg,#16a34a,#166534);display:inline-flex;align-items:center;justify-content:center;gap:.4rem}
        .nh-send:disabled{opacity:.65;cursor:progress}
        .dark .nh-p{background:#0b1c14;border-color:rgba(134,239,172,.16)}
        .dark .nh-body{background:rgba(255,255,255,.03)}
        .dark .nh-r,.dark .nh-form textarea{background:rgba(255,255,255,.06);color:#e8f6ee;border-color:rgba(134,239,172,.16)}
        @media (prefers-reduced-motion:reduce){.nh-root *{transition:none!important}}
    </style>

    {{-- Teaser --}}
    <div class="nh-teaser" x-show="teaser && !open" x-transition x-cloak @click="open = true; teaser = false">
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

        <div class="nh-body">
            <div class="nh-intro">{{ __('app.chat.float_intro') }}</div>

            @foreach ($conversations as $c)
                @php($opening = optional($c->messages->first(fn ($m) => $m->author === \App\Domain\Support\MessageAuthor::Customer))->body ?? $c->subject)
                @php($reply = $c->messages->last(fn ($m) => $m->author->isStaffSide()))
                <div class="nh-c">
                    <div class="nh-q">{{ $opening }}</div>
                    @if ($reply)
                        <div class="nh-r"><b>@svg('heroicon-o-lifebuoy', '', ['style' => 'width:.9em;height:.9em;display:inline;vertical-align:-.1em']) {{ __('app.chat.reply_label') }}</b>{{ $reply->body }}</div>
                    @else
                        <div class="nh-meta">{{ __('app.chat.status_new') }}</div>
                    @endif

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
            <textarea wire:model="message" rows="2" placeholder="{{ __('app.chat.placeholder') }}" maxlength="2000"></textarea>
            @error('message') <p class="nh-err">{{ $message }}</p> @enderror
            <button type="submit" class="nh-send" wire:target="send" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="send">@svg('heroicon-o-paper-airplane', '', ['style' => 'width:1em;height:1em']) {{ __('app.chat.send') }}</span>
                <span wire:loading wire:target="send">{{ __('app.chat.sending') }}</span>
            </button>
        </form>
    </div>

    {{-- Launcher --}}
    <button type="button" class="nh-l" @click="open = !open; teaser = false" aria-label="{{ __('app.chat.title') }}">
        @svg('heroicon-o-chat-bubble-left-right')
    </button>
</div>
