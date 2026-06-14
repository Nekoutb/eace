<x-filament-panels::page>
    <p class="text-sm text-gray-500 dark:text-gray-400">
        {{ __('app.ui.engine_intro') }}
    </p>

    <div class="grid gap-6 md:grid-cols-2">
        {{-- Phase A — invoice recording --}}
        <section class="rounded-xl border border-gray-200 bg-white p-6 dark:border-white/10 dark:bg-gray-900">
            <div class="flex items-start gap-4">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-500/10">
                    <x-filament::icon icon="heroicon-o-document-plus" class="h-6 w-6" />
                </span>
                <div class="space-y-1">
                    <h3 class="text-base font-medium text-gray-950 dark:text-white">
                        {{ __('app.stage.recording') }}
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('app.ui.phase_recording_desc') }}
                    </p>
                </div>
            </div>
            <div class="mt-5">
                {{ $this->recordAction }}
            </div>
        </section>

        {{-- Phase B — invoice settlement --}}
        <section class="rounded-xl border border-gray-200 bg-white p-6 dark:border-white/10 dark:bg-gray-900">
            <div class="flex items-start gap-4">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-300">
                    <x-filament::icon icon="heroicon-o-queue-list" class="h-6 w-6" />
                </span>
                <div class="space-y-1">
                    <h3 class="text-base font-medium text-gray-950 dark:text-white">
                        {{ __('app.stage.settlement') }}
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('app.ui.phase_settlement_desc') }}
                    </p>
                </div>
            </div>
            <div class="mt-5">
                {{ $this->settleAction }}
            </div>
        </section>
    </div>
</x-filament-panels::page>
