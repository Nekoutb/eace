{{-- Elite Advisors brand mark + wordmark. Uses currentColor so it adapts to the
     Filament light/dark top bar; the gold accent stays constant. To swap in the
     exact brand asset later, drop a file at public/img/logo.svg and replace this
     markup with: <img src="{{ asset('img/logo.svg') }}" style="height:2rem"> --}}
<svg viewBox="0 0 252 48" style="height:2.05rem;width:auto;display:block" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Elite Advisors">
    <g fill="currentColor">
        <rect x="2" y="9" width="30" height="7" rx="2"/>
        <rect x="2" y="20.5" width="20" height="7" rx="2"/>
        <rect x="2" y="32" width="30" height="7" rx="2"/>
    </g>
    <text x="46" y="30.5" font-family="ui-sans-serif,system-ui,'Segoe UI',Roboto,Arial,sans-serif"
          font-size="18.5" font-weight="700" letter-spacing="1.2">
        <tspan fill="currentColor">ELITE</tspan><tspan dx="6" fill="#b08d57" font-weight="600">ADVISORS</tspan>
    </text>
</svg>
