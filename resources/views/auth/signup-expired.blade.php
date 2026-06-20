<x-guest-layout :title="__('app.signup.expired_heading')">
    <main class="card">
        <x-signup-stepper :step="2" />

        <h1 class="brand">{{ __('app.signup.expired_heading') }}</h1>
        <p class="sub">{{ __('app.signup.expired_body') }}</p>

        <a class="btn" href="{{ route('signup') }}">{{ __('app.signup.expired_restart') }}</a>
    </main>
</x-guest-layout>
