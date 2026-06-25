<!DOCTYPE html>
<html lang="{{ str_starts_with(app()->getLocale(), 'fr') ? 'fr' : 'en' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Elite Advisors Academy</title>
<style>
  :root{--red:#E2382B;--ink:#111;--muted:#6B6B6B;--paper:#F6F6F4;--line:#e7e6e1}
  *{box-sizing:border-box}
  body{margin:0;font-family:"Inter","Segoe UI",system-ui,Arial,sans-serif;background:var(--paper);color:var(--ink);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
  .card{background:#fff;border:1px solid var(--line);border-radius:18px;max-width:420px;width:100%;padding:34px 32px;box-shadow:0 10px 40px rgba(20,20,20,.06)}
  .brand{display:flex;align-items:center;gap:11px;justify-content:center;margin-bottom:6px}
  .brand svg{width:34px;height:27px}
  .brand .nm{font-weight:800;letter-spacing:.16em;font-size:15px}
  .brand .nm .ad{color:var(--red)}
  .ac{text-align:center;font-size:11px;letter-spacing:.28em;text-transform:uppercase;color:var(--muted);margin-bottom:22px}
  h1{font-size:20px;text-align:center;margin:0 0 4px}
  .sub{text-align:center;color:var(--muted);font-size:13.5px;margin:0 0 22px}
  label{display:block;font-size:13px;font-weight:600;margin:14px 0 5px}
  input[type=password]{width:100%;border:1px solid var(--line);border-radius:11px;padding:11px 13px;font-size:15px;font-family:inherit}
  input:focus{outline:none;border-color:var(--red);box-shadow:0 0 0 3px rgba(226,56,43,.14)}
  button.primary{width:100%;margin-top:20px;background:var(--ink);color:#fff;border:0;border-radius:11px;padding:12px;font-size:15px;font-weight:700;cursor:pointer}
  button.primary:hover{background:#000}
  .err{background:#fdeceb;border:1px solid #f5c4c0;color:#a32d2d;border-radius:11px;padding:10px 13px;font-size:13.5px;margin-bottom:16px}
  .signout{display:block;text-align:center;margin-top:16px}
  .signout button{background:none;border:0;color:var(--muted);font-size:12px;cursor:pointer}
</style>
</head>
<body>
  <div class="card">
    <div class="brand">
      <svg viewBox="0 0 40 32" aria-label="Elite Advisors"><rect x="0" y="0" width="40" height="7" rx="1" fill="#E2382B"/><rect x="0" y="12.5" width="26" height="7" rx="1" fill="#E2382B"/><rect x="0" y="25" width="40" height="7" rx="1" fill="#E2382B"/></svg>
      <span class="nm">ELITE&nbsp;<span class="ad">ADVISORS</span></span>
    </div>
    <div class="ac">Academy</div>
    <h1>{{ __('academy.pw_change_title') }}</h1>
    <p class="sub">{{ __('academy.pw_change_sub') }}</p>

    @if ($errors->any())
      <div class="err">{{ $errors->first() }}</div>
    @endif

    <form method="post" action="{{ route('academy.password.update') }}">
      @csrf
      <label for="password">{{ __('academy.pw_new') }}</label>
      <input id="password" name="password" type="password" autocomplete="new-password" autofocus required>
      <label for="password_confirmation">{{ __('academy.pw_confirm') }}</label>
      <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
      <button type="submit" class="primary">{{ __('academy.pw_save') }}</button>
    </form>

    <form method="post" action="{{ route('academy.logout') }}" class="signout">
      @csrf
      <button type="submit">{{ __('academy.sign_out') }}</button>
    </form>
  </div>
</body>
</html>
