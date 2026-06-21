@php($fr = app()->getLocale() === 'fr')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $fr ? 'Merci' : 'Thank you' }} — Elite Advisors Compliance Engine</title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1.5rem;
            color:#0f231a;font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;
            background:linear-gradient(180deg,#f4fdf7,#ecfdf3)}
        .card{max-width:480px;text-align:center;background:#fff;border:1px solid rgba(21,128,61,.18);border-radius:20px;
            padding:2.4rem 2rem;box-shadow:0 18px 44px rgba(20,83,45,.12)}
        .badge{display:inline-flex;align-items:center;justify-content:center;width:3.4rem;height:3.4rem;border-radius:18px;
            color:#fff;background:linear-gradient(150deg,#15803d,#14532d);box-shadow:0 10px 24px rgba(20,83,45,.4);margin-bottom:1rem}
        .badge svg{width:1.8rem;height:1.8rem}
        h1{margin:0 0 .5rem;font-size:1.4rem;font-weight:800;color:#14532d}
        p{margin:0;color:#46685a;font-size:.95rem;line-height:1.6}
    </style>
</head>
<body>
<div class="card">
    <span class="badge">@svg('heroicon-o-check', '', ['aria-hidden' => 'true'])</span>
    <h1>{{ $fr ? 'Merci pour votre retour !' : 'Thank you for your feedback!' }}</h1>
    <p>{{ $fr
        ? 'Vos réponses anonymes nous aident à améliorer le service. Bonne journée.'
        : 'Your anonymous answers help us improve the service. Have a great day.' }}</p>
</div>
</body>
</html>
