@php($messages = $conversation->messages)

<div class="cs-thread">
    <style>
        .cs-thread{display:flex;flex-direction:column;gap:.6rem;max-height:46vh;overflow:auto;padding:.25rem .1rem;font-family:inherit}
        .cs-msg{padding:.6rem .8rem;border-radius:13px;max-width:85%;font-size:.88rem;line-height:1.55;white-space:pre-wrap;word-break:break-word}
        .cs-msg--customer{align-self:flex-start;background:#f1f5f9;border:1px solid #e2e8f0;color:#0f172a}
        .cs-msg--agent{align-self:flex-end;background:#dcfce7;border:1px solid #bbf7d0;color:#14532d}
        .cs-msg--bot{align-self:flex-end;background:#e0f2fe;border:1px solid #bae6fd;color:#075985}
        .cs-msg__meta{display:block;font-size:.68rem;opacity:.75;margin-bottom:.25rem;font-weight:700}
        .cs-empty{color:#6b7280;font-size:.85rem}
        .dark .cs-msg--customer{background:rgba(255,255,255,.06);border-color:rgba(255,255,255,.12);color:#e2e8f0}
        .dark .cs-msg--agent{background:rgba(22,101,52,.4);border-color:rgba(134,239,172,.2);color:#bbf7d0}
        .dark .cs-msg--bot{background:rgba(7,89,133,.4);border-color:rgba(125,211,252,.2);color:#bae6fd}
    </style>

    @forelse ($messages as $m)
        <div class="cs-msg cs-msg--{{ $m->author->value }}">
            <span class="cs-msg__meta">{{ $m->author->label() }}@if ($m->author_name) · {{ $m->author_name }}@endif · {{ $m->created_at?->format('d/m/Y H:i') }}</span>
            {{ $m->body }}
        </div>
    @empty
        <p class="cs-empty">{{ __('support.console.no_messages') }}</p>
    @endforelse
</div>
