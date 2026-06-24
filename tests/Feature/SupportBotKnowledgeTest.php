<?php

declare(strict_types=1);

use App\Domain\Auth\UserRole;
use App\Filament\App\Pages\Faq;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Support\ClaudeSupportBot;
use App\Support\SupportKnowledge;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('ships a non-empty, well-formed bilingual knowledge base', function () {
    $en = SupportKnowledge::faq('en');
    $fr = SupportKnowledge::faq('fr');

    expect($en)->toBeArray()->not->toBeEmpty()
        ->and(count($fr))->toBe(count($en))
        ->and(SupportKnowledge::escalationTopics())->not->toBeEmpty();

    foreach ($en as $entry) {
        expect($entry)->toHaveKeys(['category', 'q', 'a'])
            ->and(trim($entry['q']))->not->toBe('')
            ->and(trim($entry['a']))->not->toBe('');
    }

    // The French rendering is actually translated, not a copy of English.
    expect($fr[0]['a'])->not->toBe($en[0]['a']);
});

it('builds a hardened system prompt: limits, confidentiality, anti-manipulation, escalation + KB', function () {
    $bot = new ClaudeSupportBot('test-key', 'claude-test');

    $method = new ReflectionMethod($bot, 'system');
    $method->setAccessible(true);
    $prompt = (string) $method->invoke($bot, 'Acme Co', 'en');

    expect($prompt)
        ->toContain('Acme Co')                 // the workspace is named
        ->toContain('greet back')               // greetings are answered, not escalated
        ->toContain('# Greetings')              // the bot still has the greeting small talk
        ->toContain('tamper-proof')             // a real KB answer (data storage) is embedded
        ->toContain('# Data & evidence')        // categories are grouped
        ->toContain('# Our technology')         // the "what we do, not how" KB entries
        ->toContain('HARD LIMITS')              // the behavioural limits section
        ->toContain('NEVER REVEAL')             // the proprietary confidentiality section
        ->toContain('the methods, algorithms')  // a concrete confidential item is embedded
        ->toContain('ANTI-MANIPULATION')        // jailbreak / prompt-extraction resistance
        ->toContain('Pricing, plans, billing'); // the escalation policy is embedded
});

it('localises the reply language in the prompt', function () {
    $bot = new ClaudeSupportBot('test-key', 'claude-test');
    $method = new ReflectionMethod($bot, 'system');
    $method->setAccessible(true);

    expect((string) $method->invoke($bot, 'W', 'fr'))->toContain('French')
        ->and((string) $method->invoke($bot, 'W', 'en'))->toContain('English');
});

it('groups the FAQ by category in each language, without the bot-only greetings', function () {
    expect(SupportKnowledge::grouped('en'))->toHaveKey('Data & evidence')
        ->and(SupportKnowledge::grouped('en'))->toHaveKey('Our technology')
        ->and(SupportKnowledge::grouped('en'))->not->toHaveKey('Greetings')
        ->and(SupportKnowledge::grouped('fr'))->toHaveKey('Données et preuves')
        ->and(SupportKnowledge::grouped('fr'))->not->toHaveKey('Salutations')
        // …but the bot still has the small talk.
        ->and(collect(SupportKnowledge::botKnowledge('en'))->pluck('category'))->toContain('Greetings');
});

it('renders the searchable FAQ page for a workspace user', function () {
    app()->setLocale('en');
    $tenant = Tenant::create(['id' => 'faqpage']);
    tenancy()->initialize($tenant);
    Filament::setCurrentPanel('app');
    $user = User::query()->create([
        'name' => 'U', 'email' => 'u@faq.test',
        'password' => Hash::make('secret123'), 'role' => UserRole::Recorder,
    ]);
    test()->actingAs($user);

    Livewire::test(Faq::class)
        ->assertOk()
        ->assertSee('Data & evidence')
        ->assertSee('How do I record an invoice?')
        ->assertDontSee('How are you?'); // greetings are hidden from the FAQ

    tenancy()->end();
    $tenant->delete();
});
