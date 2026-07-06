<?php

declare(strict_types=1);

namespace App\Services\Firm;

use App\Models\FirmMember;

/**
 * The result of adding a firm member: the member, plus the one-time temporary
 * password to show ONCE when the firm didn't set the member's own password
 * (null when a password was supplied). Mirrors ProvisionedClient.
 */
final readonly class AddedMember
{
    public function __construct(
        public FirmMember $member,
        public ?string $temporaryPassword = null,
    ) {}
}
