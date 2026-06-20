@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' — ' : '' }}Elite Advisors Compliance Engine</title>
    <style>
        :root{--g100:#dcfce7;--g200:#bbf7d0;--g500:#22c55e;--g600:#16a34a;--g700:#15803d;--g800:#166534;
            --ink:#0f231a;--ink-soft:#46685a;--line:rgba(21,128,61,.18)}
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;
            padding:2rem 1rem;font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;
            color:var(--ink);background:linear-gradient(135deg,#f0fdf4,#dcfce7 60%,#bbf7d0)}
        a{color:var(--g700)}
        .card{width:100%;max-width:540px;background:rgba(255,255,255,.74);backdrop-filter:blur(14px);
            border:1px solid var(--line);border-radius:20px;padding:2rem 2.1rem;box-shadow:0 24px 60px rgba(21,128,61,.14)}
        .brand{font-size:1.3rem;font-weight:800;color:var(--g800);margin:0 0 .15rem}
        .sub{color:var(--ink-soft);font-size:.92rem;margin:0 0 1.3rem;line-height:1.5}
        label{display:block;font-size:.8rem;font-weight:700;color:var(--g800);margin:.85rem 0 .3rem}
        input{width:100%;padding:.62rem .75rem;border-radius:11px;border:1px solid var(--line);
            background:rgba(255,255,255,.9);font-size:.95rem;color:var(--ink);outline:none}
        input:focus{border-color:var(--g500);box-shadow:0 0 0 3px rgba(34,197,94,.25)}
        .help{font-size:.74rem;color:var(--ink-soft);margin:.3rem 0 0}
        .err{font-size:.78rem;color:#b91c1c;margin:.3rem 0 0}
        .btn{display:inline-block;text-align:center;margin-top:1.4rem;width:100%;padding:.75rem;border:0;border-radius:12px;
            cursor:pointer;background:var(--g600);color:#fff;font-size:.97rem;font-weight:700;text-decoration:none}
        .btn:hover{background:var(--g700)}
        .btn-ghost{background:transparent;color:var(--g700);border:1px solid var(--line)}
        .btn-ghost:hover{background:rgba(255,255,255,.6)}
        .foot{margin-top:1.1rem;text-align:center;font-size:.85rem;color:var(--ink-soft)}
        .foot a{color:var(--g700);font-weight:600;text-decoration:none}
        .note{background:rgba(220,252,231,.7);border:1px solid var(--line);border-radius:12px;padding:.8rem 1rem;
            font-size:.85rem;line-height:1.6;color:var(--ink);margin:.6rem 0}
        code{background:rgba(21,128,61,.08);border-radius:6px;padding:.1rem .35rem;font-size:.9rem}
        .ok{background:#ecfdf5;border:1px solid rgba(21,128,61,.25);color:var(--g800);border-radius:11px;
            padding:.6rem .85rem;font-size:.85rem;margin:0 0 1rem}
        /* Stepper */
        .steps{display:flex;gap:.4rem;margin:0 0 1.3rem;list-style:none;padding:0}
        .steps li{flex:1;text-align:center;font-size:.72rem;font-weight:700;color:var(--ink-soft);
            border-top:3px solid var(--line);padding-top:.5rem}
        .steps li.active{color:var(--g700);border-top-color:var(--g600)}
        .steps li.done{color:var(--g700);border-top-color:var(--g500)}
        /* Landing */
        .hero{width:100%;max-width:720px;text-align:center}
        .hero h1{font-size:1.9rem;font-weight:800;color:var(--g800);margin:.2rem 0 .4rem}
        .hero p.lede{font-size:1.02rem;color:var(--ink-soft);line-height:1.6;margin:0 auto 1.5rem;max-width:560px}
        .cta-row{display:flex;gap:.8rem;justify-content:center;flex-wrap:wrap;margin-bottom:1.6rem}
        .cta-row .btn{width:auto;margin-top:0;padding:.75rem 1.5rem}
        .points{display:grid;grid-template-columns:1fr;gap:.6rem;max-width:560px;margin:0 auto;text-align:left}
        .points li{background:rgba(255,255,255,.7);border:1px solid var(--line);border-radius:12px;
            padding:.7rem .9rem;font-size:.88rem;color:var(--ink);list-style:none}
        .admin-link{margin-top:1.4rem;font-size:.8rem}
        .lang{margin-top:1.2rem;font-size:.78rem;color:var(--ink-soft)}
    </style>
</head>
<body>
    {{ $slot }}
    <p class="lang">
        <a href="{{ route('locale.switch', 'fr') }}">Français</a> ·
        <a href="{{ route('locale.switch', 'en') }}">English</a>
    </p>
</body>
</html>
