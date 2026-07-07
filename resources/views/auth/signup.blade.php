<x-guest-layout :title="__('app.signup.title')">
    <main class="card">
        <x-signup-stepper :step="1" />

        <h1 class="brand">{{ __('app.signup.title') }}</h1>
        <p class="sub">{{ __('app.signup.subtitle') }}</p>

        <form method="POST" action="{{ route('signup') }}">
            @csrf
            {{-- Who is signing up? A single company, or an accounting firm that will
                 manage several client accounts from one umbrella. --}}
            <label>{{ __('app.signup.account_type') }}</label>
            <div style="display:grid;gap:.55rem;margin:.25rem 0 .35rem">
                @foreach (['company', 'firm'] as $type)
                    <label for="type_{{ $type }}" style="display:flex;gap:.6rem;align-items:flex-start;border:1.5px solid {{ old('account_type', 'company') === $type ? 'var(--g500, #22c55e)' : 'rgba(21,128,61,.2)' }};border-radius:12px;padding:.65rem .8rem;cursor:pointer;font-weight:500">
                        <input id="type_{{ $type }}" type="radio" name="account_type" value="{{ $type }}"
                               {{ old('account_type', 'company') === $type ? 'checked' : '' }}
                               style="width:auto;margin-top:.2rem"
                               onchange="this.closest('form').querySelectorAll('[data-type-card]').forEach(c=>c.style.borderColor='rgba(21,128,61,.2)');this.closest('label').style.borderColor='var(--g500, #22c55e)'">
                        <span data-type-card>
                            <strong style="display:block;font-size:.9rem">{{ __('app.signup.account_type_'.$type) }}</strong>
                            <span style="font-size:.78rem;color:var(--ink-soft,#46685a)">{{ __('app.signup.account_type_'.$type.'_help') }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            @error('account_type') <p class="err">{{ $message }}</p> @enderror

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
