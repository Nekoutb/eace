<x-guest-layout :title="app()->getLocale() === 'fr' ? 'Politique de confidentialité' : 'Privacy notice'">
    @include('partials.privacy-notice')

    <p style="text-align:center;margin-top:1.3rem">
        <a href="{{ url('/app') }}" style="font-size:.85rem;font-weight:600;color:#15803d;text-decoration:none">← {{ app()->getLocale() === 'fr' ? 'Retour à la connexion' : 'Back to sign in' }}</a>
    </p>
</x-guest-layout>
