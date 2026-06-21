<?php

declare(strict_types=1);

namespace App\Services\Support;

/**
 * Answers a customer's support message — the live Claude implementation in
 * production, a no-network fake (always escalate) in dev/tests. The implementation
 * MUST never give fiscal or legal advice; it escalates anything it cannot safely
 * resolve.
 */
interface SupportBotConnector
{
    /**
     * Answer the customer's latest message, given the prior conversation so far so
     * follow-ups are understood in context.
     *
     * @param  list<array{role: string, text: string}>  $history  Earlier turns
     *                                                            (role: user|assistant), oldest first.
     */
    public function respond(string $question, string $workspace, string $locale, array $history = []): BotReply;
}
