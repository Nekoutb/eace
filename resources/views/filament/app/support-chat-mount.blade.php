{{-- The floating in-app support chat — only for a signed-in WORKSPACE user (a tenant
     context is set). A landlord/central session on the app panel has no workspace, so
     mounting the chat (which scopes itself to tenant()) would error — guard on it.
     Persisted across Filament's SPA navigation so it stays open. --}}
@auth
    @if (tenant() !== null)
        @persist('support-chat')
            @livewire(\App\Livewire\SupportChat::class)
        @endpersist
    @endif
@endauth
