<?php

declare(strict_types=1);

namespace App\Services\Support;

use App\Domain\Support\ConversationNature;

/**
 * No-network fallback used in dev/tests or when no Anthropic key is set: it never
 * pretends to resolve anything — it returns a polite holding reply and leaves the
 * conversation for a human. Safe by construction (never fabricates an answer).
 */
final class FakeSupportBot implements SupportBotConnector
{
    public function respond(string $question, string $workspace, string $locale): BotReply
    {
        return new BotReply(
            reply: (string) __('support.bot.holding'),
            resolved: false,
            nature: ConversationNature::Question,
            isComplaint: false,
            sentiment: 'neutral',
        );
    }
}
