<?php

declare(strict_types=1);

use App\Services\Support\ClaudeSupportBot;

it('ships a non-empty, well-formed knowledge base', function () {
    $faq = config('support_kb.faq');
    $escalate = config('support_kb.escalate');

    expect($faq)->toBeArray()->not->toBeEmpty()
        ->and($escalate)->toBeArray()->not->toBeEmpty();

    foreach ($faq as $entry) {
        expect($entry)->toHaveKeys(['category', 'q', 'a'])
            ->and(trim((string) $entry['a']))->not->toBe('');
    }
});

it('builds the system prompt from the knowledge base, with greeting + escalation guidance', function () {
    $bot = new ClaudeSupportBot('test-key', 'claude-test');

    $method = new ReflectionMethod($bot, 'system');
    $method->setAccessible(true);
    $prompt = (string) $method->invoke($bot, 'Acme Co', 'en');

    expect($prompt)
        ->toContain('Acme Co')                 // the workspace is named
        ->toContain('greet them back')          // greetings are answered, not escalated
        ->toContain('tamper-proof')             // a real KB answer (data storage) is embedded
        ->toContain('# Data & evidence')        // categories are grouped
        ->toContain('Pricing, plans, billing')  // the escalation policy is embedded
        ->toContain('answer them');             // explicit "don't hand off ordinary questions"
});

it('localises the reply language in the prompt', function () {
    $bot = new ClaudeSupportBot('test-key', 'claude-test');
    $method = new ReflectionMethod($bot, 'system');
    $method->setAccessible(true);

    expect((string) $method->invoke($bot, 'W', 'fr'))->toContain('French')
        ->and((string) $method->invoke($bot, 'W', 'en'))->toContain('English');
});
