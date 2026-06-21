<?php

declare(strict_types=1);

namespace App\Domain\Support;

/**
 * The nature of a customer-service request — drives triage, the "requests by
 * nature" and "complaints by nature" analytics, and the complaint-rate KPI.
 */
enum ConversationNature: string
{
    case Question = 'question';
    case HowTo = 'how_to';
    case Complaint = 'complaint';
    case Bug = 'bug';
    case Billing = 'billing';
    case Feature = 'feature';
    case Other = 'other';

    public function label(): string
    {
        return (string) __('support.nature.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Complaint => 'danger',
            self::Bug => 'warning',
            self::Billing => 'info',
            self::Feature, self::Other => 'gray',
            default => 'primary',
        };
    }

    public function isComplaint(): bool
    {
        return $this === self::Complaint;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
