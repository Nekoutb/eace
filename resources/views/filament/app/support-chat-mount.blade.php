{{-- The floating in-app support chat — only for signed-in workspace users (tenant
     context is set), persisted across Filament's SPA navigation so it stays open. --}}
@auth
    @persist('support-chat')
        @livewire(\App\Livewire\SupportChat::class)
    @endpersist
@endauth
