@if (config('niu.self_service_signup'))
    <div style="margin-top:1.25rem;text-align:center;font-size:.85rem">
        <a href="{{ url('/register') }}" style="color:#15803d;font-weight:600;text-decoration:none">{{ __('app.signup.link') }} →</a>
    </div>
@endif

<div style="margin-top:.9rem;text-align:center;font-size:.82rem">
    <a href="{{ url('/security') }}" style="color:#46685a;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem;justify-content:center">@svg('heroicon-m-lock-closed', '', ['style' => 'width:1em;height:1em', 'aria-hidden' => 'true']){{ app()->getLocale() === 'fr' ? 'Sécurité & protection des données' : 'Security & data protection' }}</a>
</div>
