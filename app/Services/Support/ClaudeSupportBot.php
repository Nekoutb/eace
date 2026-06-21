<?php

declare(strict_types=1);

namespace App\Services\Support;

use App\Domain\Support\ConversationNature;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The live customer-service bot: Anthropic's Claude with a forced answer_support
 * tool, so it returns a structured {reply, resolved, nature, is_complaint,
 * sentiment} every time. A tight product knowledge base keeps it on-topic, and a
 * hard guardrail makes it ESCALATE (resolved=false) anything that is a complaint or
 * touches fiscal/legal/tax advice rather than answer it. The API key comes from
 * config — never handled in code.
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

        return <<<TXT
            You are the customer-support assistant for the "Elite Advisors Compliance Engine",
            a SaaS that helps Cameroon businesses verify supplier fiscal compliance. Its features:
            recording invoices (upload an ACF tax-clearance certificate + scanned invoices, which
            are OCR'd and run through four checks + the CGI art. 150 conformity checks), settling
            payments (batch NIU verification), an evidence vault (DGI/Harmony2 screenshots, trusted
            timestamps), a dashboard with KPIs, Settings (business identity), user roles
            (Administrator / Invoice recording / Invoice settler), and mandatory two-factor auth.
            The workspace asking is "{$workspace}".

            Answer ONLY questions about how to use the product, navigate it, or manage the account,
            and only when you are confident. Be concise, friendly, and reply in {$lang}.

            HARD RULES — set resolved=false (a human will take over) whenever the message:
            - is a complaint or expresses dissatisfaction;
            - asks for fiscal, tax, legal or accounting ADVICE, or whether a specific supplier /
              invoice / NIU is compliant (you cannot see their data and must never advise on this);
            - concerns billing, refunds, pricing, contracts, or anything you are unsure about.
            In those cases still write a brief, polite holding reply saying a specialist will follow up.
            Never invent features, prices, or legal conclusions.
            TXT;
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
                    'resolved' => ['type' => 'boolean', 'description' => 'True ONLY if you fully answered a product/how-to question. False for complaints, fiscal/legal advice, or anything uncertain.'],
                    'nature' => ['type' => 'string', 'enum' => ['question', 'how_to', 'complaint', 'bug', 'billing', 'feature', 'other']],
                    'is_complaint' => ['type' => 'boolean', 'description' => 'True if the customer is dissatisfied or complaining.'],
                    'sentiment' => ['type' => 'string', 'enum' => ['positive', 'neutral', 'negative']],
                ],
            ],
        ];
    }
}
