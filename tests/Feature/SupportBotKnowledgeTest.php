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

it('groups the FAQ by category in each language', function () {
    expect(SupportKnowledge::grouped('en'))->toHaveKey('Data & evidence')
        ->and(SupportKnowledge::grouped('fr'))->toHaveKey('Données et preuves');
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
        ->assertSee('How do I record an invoice?');

    tenancy()->end();
    $tenant->delete();
});
