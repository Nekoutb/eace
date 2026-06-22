<x-guest-layout :title="__('app.signup.title')">
    <main class="card">
        <x-signup-stepper :step="1" />

        <h1 class="brand">{{ __('app.signup.title') }}</h1>
        <p class="sub">{{ __('app.signup.subtitle') }}</p>

        <form method="POST" action="{{ route('signup') }}">
            @csrf
            <label for="org_name">{{ __('app.signup.org_name') }}</label>
            <input id="org_name" name="org_name" value="{{ old('org_name') }}" required autofocus maxlength="255">
            @error('org_name') <p class="err">{{ $message }}</p> @enderror

            <label for="name">{{ __('app.signup.your_name') }}</label>
            <input id="name" name="name" value="{{ old('name') }}" required maxlength="255">
            @error('name') <p class="err">{{ $message }}</p> @enderror

            <label for="email">{{ __('app.signup.email') }}</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="255">
            <p class="help">{{ __('app.signup.email_help') }}</p>
            @error('email') <p class="err">{{ $message }}</p> @enderror

            <label for="consent" style="display:flex;gap:.5rem;align-items:flex-start;font-weight:500;color:var(--ink);font-size:.84rem;margin-top:1.1rem">
                <input id="consent" type="checkbox" name="consent" value="1" required {{ old('consent') ? 'checked' : '' }} style="width:auto;margin-top:.15rem">
                <span>@if(app()->getLocale() === 'fr') J'ai lu et j'accepte la <a href="{{ url('/privacy') }}" target="_blank" rel="noopener">politique de confidentialité</a>. @else I have read and accept the <a href="{{ url('/privacy') }}" target="_blank" rel="noopener">privacy notice</a>. @endif</span>
            </label>
            @error('consent') <p class="err">{{ $message }}</p> @enderror

            <button class="btn" type="submit">{{ __('app.signup.submit') }}</button>
        </form>

        <p class="foot">{{ __('app.signup.have_account') }}
            <a href="{{ url('/app/login') }}">{{ __('app.signup.signin') }}</a>
        </p>
    </main>
</x-guest-layout>
