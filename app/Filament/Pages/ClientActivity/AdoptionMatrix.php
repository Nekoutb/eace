<?php

namespace App\Filament\Pages\ClientActivity;

use App\Services\Activity\ClientActivityService;
use Filament\Widgets\Widget;

/**
 * The client-adoption matrix for the Client activity page: one column per client,
 * one row per expected usage step (sign-in, 2FA, company settings, team, ACF,
 * recording, settlement) — each cell shows whether the client has reached that
 * step, so the operator can engage them at exactly the right moment. Not
 * discovered on the dashboard — rendered only by the ClientActivity page.
 */
class AdoptionMatrix extends Widget
{
    protected string $view = 'filament.widgets.adoption-matrix';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'steps' => ClientActivityService::adoptionSteps(),
            'clients' => app(ClientActivityService::class)->adoptionMatrix(),
        ];
    }
}
