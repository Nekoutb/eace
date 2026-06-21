<?php

declare(strict_types=1);

namespace App\Services\Support;

use App\Domain\Support\ConversationNature;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The live customer-service bot: Anthropic's Claude with a forced answer_support
 * tool, so it returns a structured {reply, resolved, nature, is_complaint,
 * sentiment} every time. It answers BY DEFAULT from the product knowledge base in
 * config/support_kb.php — greetings, how-to, navigation, security and data-storage
 * questions are all handled in-chat (resolved=true). It hands off to a human
 * (resolved=false → Telegram) ONLY for the `escalate` topics in that config
 * (complaints, billing/pricing, account changes, fiscal/legal advice, bugs, or an
 * explicit request for a person). The API key comes from config — never in code.
 */
final class ClaudeSupportBot implements SupportBotConnector
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private const VERSION = '2023-06-01';

    public function __construct(
        private string $apiKey,
        private string $model,
    ) {}

    public function respond(string $question, string $workspace, string $locale): BotReply
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => self::VERSION,
            'content-type' => 'application/json',
        ])->timeout(40)->post(self::ENDPOINT, [
            'model' => $this->model,
            'max_tokens' => 1024,
            'system' => $this->system($workspace, $locale),
            'tools' => [$this->tool()],
            'tool_choice' => ['type' => 'tool', 'name' => 'answer_support'],
            'messages' => [[
                'role' => 'user',
                'content' => [['type' => 'text', 'text' => $question]],
            ]],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Support bot failed: HTTP '.$response->status());
        }

        foreach ((array) $response->json('content', []) as $block) {
            if (is_array($block)
                && ($block['type'] ?? null) === 'tool_use'
                && ($block['name'] ?? null) === 'answer_support'
                && is_array($block['input'] ?? null)
            ) {
                return $this->map($block['input']);
            }
        }

        throw new RuntimeException('Support bot returned no structured reply.');
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function map(array $input): BotReply
    {
        $nature = is_string($input['nature'] ?? null)
            ? (ConversationNature::tryFrom($input['nature']) ?? ConversationNature::Question)
            : ConversationNature::Question;

        $isComplaint = (bool) ($input['is_complaint'] ?? false) || $nature === ConversationNature::Complaint;

        return new BotReply(
            reply: trim((string) ($input['reply'] ?? '')),
            // A complaint or low confidence is never "resolved" by the bot.
            resolved: (bool) ($input['resolved'] ?? false) && ! $isComplaint,
            nature: $nature,
            isComplaint: $isComplaint,
            sentiment: is_string($input['sentiment'] ?? null) ? $input['sentiment'] : 'neutral',
        );
    }

    private function system(string $workspace, string $locale): string
    {
        $lang = str_starts_with($locale, 'fr') ? 'French' : 'English';
        $knowledge = $this->knowledgeBase();
        $escalate = $this->escalationPolicy();

        return <<<TXT
            You are the friendly customer-support assistant for "Elite Advisors Compliance Engine" (ACE),
            a SaaS that helps Cameroon businesses verify the fiscal compliance of their suppliers before
            recording invoices or settling payments. The workspace asking is "{$workspace}".
            Always reply in {$lang}, warmly and concisely (2–6 sentences).

            HOW TO RESPOND — answer by default:
            - If the customer greets you or makes small talk ("hi", "hello", "bonjour", "thanks",
              "how are you"), greet them back warmly, say who you are, and ask how you can help today.
              That is a complete answer: set resolved=true and escalate nothing.
            - Answer questions about the product, navigation, the account, security and how data is
              stored CONFIDENTLY from the KNOWLEDGE BASE below, and set resolved=true. Most messages
              should be answered, not handed off.

            HAND OFF TO A HUMAN (set resolved=false and write a short, polite holding reply saying a
            specialist will follow up) ONLY when the message is about:
            {$escalate}
            Do not hand off ordinary how-to, navigation, security or data-storage questions — answer them.

            KNOWLEDGE BASE — your ground truth; do not contradict it or invent beyond it:
            {$knowledge}

            Never invent features, prices, legal conclusions, or anything not supported above. If a
            question is not covered and is not a hand-off topic, give your best general help; only if you
            are genuinely unsure should you offer to have a specialist follow up.
            TXT;
    }

    /** The FAQ knowledge base, grouped by category, formatted for the prompt. */
    private function knowledgeBase(): string
    {
        $byCategory = [];

        foreach ((array) config('support_kb.faq', []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $byCategory[(string) ($entry['category'] ?? 'General')][] =
                'Q: '.trim((string) ($entry['q'] ?? '')).' — A: '.trim((string) ($entry['a'] ?? ''));
        }

        $lines = [];

        foreach ($byCategory as $category => $qas) {
            $lines[] = '# '.$category;
            foreach ($qas as $qa) {
                $lines[] = '- '.$qa;
            }
        }

        return implode("\n", $lines);
    }

    /** The bullet list of topics that must always be handed to a human. */
    private function escalationPolicy(): string
    {
        $lines = [];

        foreach ((array) config('support_kb.escalate', []) as $topic) {
            $lines[] = '- '.trim((string) $topic);
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<string, mixed>
     */
    private function tool(): array
    {
        return [
            'name' => 'answer_support',
            'description' => 'Return the reply to the customer plus a triage classification.',
            'input_schema' => [
                'type' => 'object',
                'required' => ['reply', 'resolved', 'nature', 'is_complaint'],
                'properties' => [
                    'reply' => ['type' => 'string', 'description' => 'The message to show the customer.'],
                    'resolved' => ['type' => 'boolean', 'description' => 'True if you answered the customer — including greetings, small talk, and any product, how-to, navigation, security or data-storage question. False ONLY for the hand-off topics: complaints, billing/pricing, account changes, fiscal/legal advice, bugs/outages, or an explicit request for a person.'],
                    'nature' => ['type' => 'string', 'enum' => ['question', 'how_to', 'complaint', 'bug', 'billing', 'feature', 'other']],
                    'is_complaint' => ['type' => 'boolean', 'description' => 'True if the customer is dissatisfied or complaining.'],
                    'sentiment' => ['type' => 'string', 'enum' => ['positive', 'neutral', 'negative']],
                ],
            ],
        ];
    }
}
