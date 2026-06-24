<?php

declare(strict_types=1);

namespace App\Services\Support;

use App\Domain\Support\ConversationNature;

/**
 * The bot's structured verdict on one customer message: the reply to show, whether
 * it actually RESOLVED the question (vs needs a human), the classified nature, and
 * a coarse sentiment. resolved is true only for product/how-to questions the bot
 * answered with confidence — never for complaints or fiscal/legal matters.
 *
 * `flag` is a team-awareness signal raised when the customer probed our proprietary
 * internals or tried to manipulate the bot: 'none' | 'proprietary_probe' |
 * 'manipulation' | 'other_customer_data'. The bot still answers safely in-chat; the
 * flag only nudges the team (it does not, by itself, escalate the conversation).
 */
final class BotReply
{
    public function __construct(
        public readonly string $reply,
        public readonly bool $resolved,
        public readonly ConversationNature $nature,
        public readonly bool $isComplaint,
        public readonly string $sentiment = 'neutral',
        public readonly string $flag = 'none',
    ) {}
}
