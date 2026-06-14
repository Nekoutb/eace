<?php

declare(strict_types=1);

namespace App\Domain\Approval;

/**
 * The headline decision. Strict: the invoice is accepted only when all four
 * checks pass; any failure rejects it.
 */
enum ApprovalDecision: string
{
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return (string) __('app.approval.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Accepted => 'success',
            self::Rejected => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Accepted => 'heroicon-o-check-badge',
            self::Rejected => 'heroicon-o-no-symbol',
        };
    }
}
