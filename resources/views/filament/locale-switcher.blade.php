<div class="flex items-center gap-1">
    @foreach (['fr' => 'FR', 'en' => 'EN'] as $code => $label)
        <a
            href="/locale/{{ $code }}"
            @class([
                'rounded-md px-2 py-1 text-xs font-medium transition',
                'bg-primary-600 text-white' => app()->getLocale() === $code,
                'text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5' => app()->getLocale() !== $code,
            ])
        >
            {{ $label }}
        </a>
    @endforeach
</div>
