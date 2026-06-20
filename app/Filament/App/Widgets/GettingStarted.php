<?php

namespace App\Filament\App\Widgets;

use App\Models\Verification;
use Filament\Widgets\Widget;

/**
 * A "getting started" card shown on the dashboard only while the workspace is
 * brand-new — nothing recorded yet. It points the user at their first actions and
 * disappears automatically once the first verification is recorded.
 */
class GettingStarted extends Widget
{
    protected string $view = 'filament.app.widgets.getting-started';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -3;

    public static function canView(): bool
    {
        return Verification::query()->doesntExist();
    }
}
