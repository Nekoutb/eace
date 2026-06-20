<x-guest-layout :title="__('app.signup.sent_heading')">
    <main class="card">
        <x-signup-stepper :step="2" />

        @if (session('status'))
            <p class="ok">{{ session('status') }}</p>
        @endif

        <h1 class="brand">{{ __('app.signup.sent_heading') }}</h1>
        <p class="sub">{{ __('app.signup.sent_body', ['email' => $email, 'hours' => $hours]) }}</p>

        <p class="note">{{ __('app.signup.sent_spam') }}</p>

        <form method="POST" action="{{ route('signup.resend') }}">
            @csrf
            <button class="btn btn-ghost" type="submit">{{ __('app.signup.resend') }}</button>
        </form>

        <p class="foot"><a href="{{ route('signup') }}">{{ __('app.signup.sent_wrong') }}</a></p>
    </main>
</x-guest-layout>
