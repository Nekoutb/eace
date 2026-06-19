@if (config('niu.self_service_signup'))
    @php($base = config('niu.tenant_base_domain', 'localhost'))
    @php($port = request()->getPort())
    @php($portPart = $port === null || in_array($port, [80, 443], true) ? '' : ':'.$port)
    @php($url = request()->getScheme().'://'.$base.$portPart.'/register')
    <div style="margin-top:1.25rem;text-align:center;font-size:.85rem">
        <a href="{{ $url }}" style="color:#15803d;font-weight:600;text-decoration:none">{{ __('app.signup.link') }} →</a>
    </div>
@endif

<div style="margin-top:.9rem;text-align:center;font-size:.82rem">
    <a href="{{ url('/security') }}" style="color:#46685a;font-weight:600;text-decoration:none">🔒 {{ app()->getLocale() === 'fr' ? 'Sécurité & protection des données' : 'Security & data protection' }}</a>
</div>
