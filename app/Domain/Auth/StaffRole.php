<?php

declare(strict_types=1);

namespace App\Domain\Auth;

/**
 * The role of a CENTRAL (landlord-side) user within the customer-service platform.
 * Distinct from the tenant-side UserRole: central users have no tenant `role`
 * column (that is how isCentralUser() works), so their standing is carried by
 * staff_role instead. The owner is the SaaS operator; supervisors and agents are
 * onboarded support staff.
 */
enum StaffRole: string
{
    case Owner = 'owner';
    case Supervisor = 'supervisor';
    case Agent = 'agent';

    public function label(): string
    {
        return (string) __('support.staff.role_'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Owner => 'danger',
            self::Supervisor => 'warning',
            self::Agent => 'primary',
        };
    }

    /** May this role onboard / manage other support staff? */
    public function canManageStaff(): bool
    {
        return $this === self::Owner || $this === self::Supervisor;
    }

    /**
     * The roles an operator may assign when onboarding — never "owner" (there is
     * exactly one, the SaaS operator).
     *
     * @return array<string, string>
     */
    public static function assignableOptions(): array
    {
        return [
            self::Supervisor->value => self::Supervisor->label(),
            self::Agent->value => self::Agent->label(),
        ];
    }
}
