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
    public function respond(string $question, string $workspace, string $locale): BotReply;
}
