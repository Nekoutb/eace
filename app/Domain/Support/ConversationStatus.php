<?php

declare(strict_types=1);

namespace App\Domain\Support;

/**
 * Where a conversation sits in its lifecycle. The "open" set (everything not
 * resolved/closed) drives the live backlog; the transitions stamp the timeline
 * fields (first_response_at, escalated_at, resolved_at) the KPIs are computed from.
 */
enum ConversationStatus: string
{
    case New = 'new';                       // raised by the customer, no reply yet
    case Open = 'open';                     // an agent (or the bot) is handling it
    case AwaitingCustomer = 'awaiting_customer';
    case Escalated = 'escalated';           // handed to a human / pushed to Telegram
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return (string) __('support.status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::Open => 'primary',
            self::AwaitingCustomer => 'info',
            self::Escalated => 'danger',
            self::Resolved => 'success',
            self::Closed => 'gray',
        };
    }

    /** Still demanding attention — counted in the live backlog. */
    public function isOpen(): bool
    {
        return in_array($this, self::openCases(), true);
    }

    /**
     * @return array<int, self>
     */
    public static function openCases(): array
    {
        return [self::New, self::Open, self::AwaitingCustomer, self::Escalated];
    }

    /**
     * @return array<int, string>
     */
    public static function openValues(): array
    {
        return array_map(static fn (self $s): string => $s->value, self::openCases());
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
