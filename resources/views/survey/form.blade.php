@php($fr = app()->getLocale() === 'fr')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $fr ? 'Votre avis' : 'Your feedback' }} — Elite Advisors Compliance Engine</title>
    <style>
        :root{--g50:#f0fdf4;--g100:#dcfce7;--g200:#bbf7d0;--g500:#22c55e;--g600:#16a34a;--g700:#15803d;--g800:#166534;--g900:#14532d;
            --ink:#0f231a;--ink-soft:#46685a;--line:rgba(21,128,61,.18)}
        *{box-sizing:border-box}
        body{margin:0;color:var(--ink);font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;
            line-height:1.6;background:linear-gradient(180deg,#f4fdf7,#ecfdf3)}
        .wrap{max-width:640px;margin:0 auto;padding:2.5rem 1.25rem 4rem}
        .hero{background:linear-gradient(150deg,var(--g700),var(--g900));color:#fff;border-radius:20px;padding:1.6rem 1.8rem;
            box-shadow:0 18px 44px rgba(20,83,45,.22);margin-bottom:1.2rem}
        .hero h1{margin:0 0 .4rem;font-size:1.45rem;font-weight:800}
        .hero p{margin:0;opacity:.93;font-size:.92rem}
        .card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:1.3rem 1.45rem;margin-bottom:1rem;
            box-shadow:0 10px 30px rgba(20,83,45,.07)}
        .q{margin:0 0 1.4rem}
        .q:last-child{margin-bottom:0}
        .q__label{display:block;font-size:.95rem;font-weight:700;color:var(--g800);margin-bottom:.55rem}
        .scale{display:flex;flex-wrap:wrap;gap:.4rem}
        .scale input{position:absolute;opacity:0;width:0;height:0}
        .scale label{cursor:pointer;min-width:2.4rem;text-align:center;padding:.5rem .2rem;border-radius:10px;font-size:.9rem;font-weight:700;
            color:var(--g800);background:var(--g50);border:1px solid var(--line);transition:all .12s;flex:1 1 auto}
        .scale label:hover{border-color:var(--g500)}
        .scale input:checked + label{background:linear-gradient(150deg,var(--g600),var(--g800));color:#fff;border-color:transparent;box-shadow:0 6px 14px rgba(20,83,45,.3)}
        .ends{display:flex;justify-content:space-between;font-size:.72rem;color:var(--ink-soft);margin-top:.35rem}
        textarea{width:100%;padding:.7rem .85rem;border-radius:12px;font-size:.92rem;color:var(--ink);background:#fff;
            border:1px solid var(--line);outline:none;resize:vertical;min-height:5rem;font-family:inherit}
        textarea:focus{border-color:var(--g500);box-shadow:0 0 0 3px rgba(34,197,94,.2)}
        .btn{border:0;cursor:pointer;width:100%;padding:.85rem 1.5rem;border-radius:13px;font-size:1rem;font-weight:800;color:#fff;
            background:linear-gradient(150deg,var(--g600),var(--g800));box-shadow:0 10px 22px rgba(20,83,45,.32)}
        .btn:hover{filter:brightness(1.05)}
        .note{font-size:.76rem;color:var(--ink-soft);text-align:center;margin-top:1rem}
        .err{font-size:.78rem;color:#b91c1c;font-weight:600;margin:.4rem 0 0}
    </style>
</head>
<body>
<div class="wrap">
    <section class="hero">
        <h1>{{ $fr ? 'Votre avis compte' : 'Your feedback matters' }}</h1>
        <p>{{ $fr
            ? 'Quelques questions sur votre expérience d’Elite Advisors Compliance Engine. Réponses anonymes — moins d’une minute.'
            : 'A few questions about your experience with the Elite Advisors Compliance Engine. Anonymous — under a minute.' }}</p>
    </section>

    <form method="POST" action="{{ route('survey.submit') }}">
        @csrf
        <input type="hidden" name="k" value="{{ $token }}">

        <div class="card">
            @php($likert = [
                'service_quality' => $fr ? 'Qualité globale du service' : 'Overall service quality',
                'ease_of_use' => $fr ? 'Facilité d’utilisation' : 'Ease of use',
                'responsiveness' => $fr ? 'Réactivité du support' : 'Support responsiveness',
            ])
            @foreach ($likert as $name => $label)
                <div class="q">
                    <span class="q__label">{{ $label }}</span>
                    <div class="scale">
                        @for ($i = 1; $i <= 5; $i++)
                            <input type="radio" id="{{ $name }}_{{ $i }}" name="{{ $name }}" value="{{ $i }}">
                            <label for="{{ $name }}_{{ $i }}">{{ $i }}</label>
                        @endfor
                    </div>
                    <div class="ends"><span>{{ $fr ? 'Médiocre' : 'Poor' }}</span><span>{{ $fr ? 'Excellent' : 'Excellent' }}</span></div>
                </div>
            @endforeach

            <div class="q">
                <span class="q__label">{{ $fr ? 'Quelle est la probabilité que vous nous recommandiez ?' : 'How likely are you to recommend us?' }}</span>
                <div class="scale">
                    @for ($i = 0; $i <= 10; $i++)
                        <input type="radio" id="rec_{{ $i }}" name="recommend" value="{{ $i }}">
                        <label for="rec_{{ $i }}">{{ $i }}</label>
                    @endfor
                </div>
                <div class="ends"><span>{{ $fr ? 'Pas du tout' : 'Not at all' }}</span><span>{{ $fr ? 'Tout à fait' : 'Extremely' }}</span></div>
            </div>

            <div class="q">
                <span class="q__label">{{ $fr ? 'Un commentaire ? (facultatif)' : 'Anything to add? (optional)' }}</span>
                <textarea name="comment" maxlength="2000" placeholder="{{ $fr ? 'Vos suggestions…' : 'Your suggestions…' }}"></textarea>
                @error('comment') <p class="err">{{ $message }}</p> @enderror
            </div>
        </div>

        <button type="submit" class="btn">{{ $fr ? 'Envoyer mon avis' : 'Send my feedback' }}</button>
        <p class="note">{{ $fr ? '🔒 Anonyme — vos réponses ne sont liées à aucun compte.' : '🔒 Anonymous — your answers are not linked to any account.' }}</p>
    </form>
</div>
</body>
</html>
