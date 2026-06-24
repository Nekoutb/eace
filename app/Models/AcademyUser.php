<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;

/**
 * A training-platform (/academy) sign-in account for an Elite Advisors staff
 * member. Central (landlord) data, authenticated by the `academy` guard with a
 * plain email + password — NO two-factor. An academy account can reach ONLY the
 * training module, never the admin console or a workspace.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property bool $is_active
 * @property ?Carbon $last_login_at
 */
class AcademyUser extends Authenticatable
{
    protected $table = 'academy_users';

    protected $guarded = ['id'];

    /** @var list<string> */
    protected $hidden = ['password', 'remember_token'];

    /** Landlord data — pin to the central connection even inside a tenant context. */
    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }
}
