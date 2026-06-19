<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('app.signup.title') }} — Elite Advisors Compliance Engine</title>
    <style>
        :root{--g100:#dcfce7;--g200:#bbf7d0;--g500:#22c55e;--g600:#16a34a;--g700:#15803d;--g800:#166534;
            --ink:#0f231a;--ink-soft:#46685a;--line:rgba(21,128,61,.18)}
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem 1rem;
            font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;color:var(--ink);
            background:linear-gradient(135deg,#f0fdf4,#dcfce7 60%,#bbf7d0)}
        .card{width:100%;max-width:560px;background:rgba(255,255,255,.72);backdrop-filter:blur(14px);
            border:1px solid var(--line);border-radius:20px;padding:2rem 2.1rem;box-shadow:0 24px 60px rgba(21,128,61,.14)}
        .brand{font-size:1.35rem;font-weight:800;color:var(--g800);margin:0 0 .15rem}
        .sub{color:var(--ink-soft);font-size:.92rem;margin:0 0 1.4rem}
        label{display:block;font-size:.8rem;font-weight:700;color:var(--g800);margin:.85rem 0 .3rem}
        .field{display:flex;align-items:stretch}
        input{width:100%;padding:.62rem .75rem;border-radius:11px;border:1px solid var(--line);background:rgba(255,255,255,.85);
            font-size:.95rem;color:var(--ink);outline:none}
        input:focus{border-color:var(--g500);box-shadow:0 0 0 3px rgba(34,197,94,.25)}
        .field input{border-top-right-radius:0;border-bottom-right-radius:0}
        .suffix{display:flex;align-items:center;padding:0 .75rem;border:1px solid var(--line);border-left:0;
            border-top-right-radius:11px;border-bottom-right-radius:11px;background:rgba(220,252,231,.7);
            color:var(--ink-soft);font-size:.85rem;white-space:nowrap}
        .row{display:grid;grid-template-columns:1fr 1fr;gap:.9rem}
        @media (max-width:520px){.row{grid-template-columns:1fr}}
        .help{font-size:.74rem;color:var(--ink-soft);margin:.3rem 0 0}
        .err{font-size:.78rem;color:#b91c1c;margin:.3rem 0 0}
        button{margin-top:1.5rem;width:100%;padding:.75rem;border:0;border-radius:12px;cursor:pointer;
            background:var(--g600);color:#fff;font-size:.97rem;font-weight:700}
        button:hover{background:var(--g700)}
        .foot{margin-top:1.1rem;text-align:center;font-size:.85rem;color:var(--ink-soft)}
        .foot a{color:var(--g700);font-weight:600;text-decoration:none}
    </style>
</head>
<body>
    <main class="card">
        <h1 class="brand">Elite Advisors Compliance Engine</h1>
        <p class="sub">{{ __('app.signup.subtitle') }}</p>

        <form method="POST" action="{{ url('/register') }}">
            @csrf
            <label for="org_name">{{ __('app.signup.org_name') }}</label>
            <input id="org_name" name="org_name" value="{{ old('org_name') }}" required autofocus>
            @error('org_name') <p class="err">{{ $message }}</p> @enderror

            <label for="subdomain">{{ __('app.signup.subdomain') }}</label>
            <div class="field">
                <input id="subdomain" name="subdomain" value="{{ old('subdomain') }}" required
                       style="text-transform:lowercase" pattern="[a-z0-9]([a-z0-9-]*[a-z0-9])?">
                <span class="suffix">.{{ config('niu.tenant_base_domain', 'localhost') }}</span>
            </div>
            <p class="help">{{ __('app.signup.subdomain_help') }}</p>
            @error('subdomain') <p class="err">{{ $message }}</p> @enderror

            <div class="row">
                <div>
                    <label for="name">{{ __('app.signup.your_name') }}</label>
                    <input id="name" name="name" value="{{ old('name') }}" required>
                    @error('name') <p class="err">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="email">{{ __('app.signup.email') }}</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required>
                    @error('email') <p class="err">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="row">
                <div>
                    <label for="password">{{ __('app.signup.password') }}</label>
                    <input id="password" name="password" type="password" required>
                    @error('password') <p class="err">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="password_confirmation">{{ __('app.signup.password_confirm') }}</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required>
                </div>
            </div>

            <button type="submit">{{ __('app.signup.submit') }}</button>
        </form>

        <p class="foot">{{ __('app.signup.have_account') }}</p>
    </main>
</body>
</html>
