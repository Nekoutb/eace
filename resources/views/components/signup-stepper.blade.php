@props(['step' => 1])
<ul class="steps">
    <li class="{{ $step == 1 ? 'active' : ($step > 1 ? 'done' : '') }}">1 · {{ __('app.signup.step_details') }}</li>
    <li class="{{ $step == 2 ? 'active' : ($step > 2 ? 'done' : '') }}">2 · {{ __('app.signup.step_verify') }}</li>
    <li class="{{ $step == 3 ? 'active' : '' }}">3 · {{ __('app.signup.step_ready') }}</li>
</ul>
