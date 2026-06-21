<?php

declare(strict_types=1);

namespace App\Domain\Support;

/**
 * Who wrote a message in a conversation thread: the customer, a support agent, or
 * the AI bot (the bot author is reserved for the auto-responder added in a later
 * phase). Used to colour the thread and to time the first human/bot response.
 */
enum MessageAuthor: string
{
    case Customer = 'customer';
    case Agent = 'agent';
    case Bot = 'bot';

    public function label(): string
    {
        return (string) __('support.author.'.$this->value);
    }

    /** A reply from the support side (agent or bot) — counts as a first response. */
    public function isStaffSide(): bool
    {
        return $this !== self::Customer;
    }
}
