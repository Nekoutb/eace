<x-guest-layout>
    <section class="hero">
        <h1>Elite Advisors Compliance Engine</h1>
        <p class="lede">{{ __('app.landing.tagline') }}</p>

        <div class="cta-row">
            <a class="btn" href="{{ route('signup') }}">{{ __('app.landing.create_cta') }}</a>
            <a class="btn btn-ghost" href="{{ url('/app/login') }}">{{ __('app.landing.signin_cta') }}</a>
        </div>

        @php($check = "width:1.05em;height:1.05em;color:#16a34a;vertical-align:-.18em;margin-right:.4rem")
        <ul class="points">
            <li>@svg('heroicon-m-check-circle', '', ['style' => $check, 'aria-hidden' => 'true']){{ __('app.landing.point_isolated') }}</li>
            <li>@svg('heroicon-m-check-circle', '', ['style' => $check, 'aria-hidden' => 'true']){{ __('app.landing.point_register') }}</li>
            <li>@svg('heroicon-m-check-circle', '', ['style' => $check, 'aria-hidden' => 'true']){{ __('app.landing.point_evidence') }}</li>
        </ul>

        <p class="admin-link"><a href="{{ url('/admin') }}">{{ __('app.landing.admin_link') }} →</a></p>
    </section>
</x-guest-layout>
