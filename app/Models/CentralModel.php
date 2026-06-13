<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Base for landlord-owned models (the shared NIU registry, Fiscalis health).
 * Pinning the connection keeps these on the central database even when the
 * code runs inside an initialised tenant — the rules engine reads the shared
 * registry from within tenant context.
 */
abstract class CentralModel extends Model
{
    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection');
    }
}
