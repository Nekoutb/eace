@props(['step' => 1])
{{-- Two steps since instant onboarding: details → workspace ready. (Legacy
     verify-first screens pass step 2 as well — they render as "ready".) --}}
<ul class="steps">
    <li class="{{ $step == 1 ? 'active' : 'done' }}">1 · {{ __('app.signup.step_details') }}</li>
    <li class="{{ $step >= 2 ? 'active' : '' }}">2 · {{ __('app.signup.step_ready') }}</li>
</ul>
