<x-guest-layout :title="__('app.signup.done_heading')">
    <main class="card">
        <x-signup-stepper :step="2" />

        <h1 class="brand">{{ __('app.signup.done_heading') }}</h1>
        <p class="sub">{{ __('app.signup.done_body', ['email' => $email]) }}</p>

        <div class="note">
            <strong>{{ __('app.signup.done_email_label') }}:</strong> <code>{{ $email }}</code><br>
            <strong>{{ __('app.signup.done_password_label') }}:</strong> <code>{{ $password }}</code>
        </div>

        <a class="btn" href="{{ $loginUrl.'?email='.rawurlencode($email) }}">{{ __('app.signup.done_login_cta') }}</a>
    </main>
</x-guest-layout>
