<?php

declare(strict_types=1);

use App\Domain\Auth\UserRole;
use App\Domain\Support\ConversationNature;
use App\Domain\Support\ConversationStatus;
use App\Domain\Support\MessageAuthor;
use App\Filament\App\Widgets\SupportChatWidget;
use App\Filament\Resources\Conversations\ConversationResource;
use App\Filament\Widgets\CustomerServiceStats;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('threads messages and stamps the response, resolution and escalation timeline', function () {
    $c = Conversation::query()->create([
        'tenant_id' => 'acme',
        'tenant_name' => 'Acme',
        'nature' => ConversationNature::Question,
        'status' => ConversationStatus::New,
    ]);

    $c->postMessage(MessageAuthor::Customer, 'How do I record an invoice?', 'Jane');
    expect($c->fresh()->first_response_at)->toBeNull();

    $c->postMessage(MessageAuthor::Agent, 'Open the Compliance Engine…', 'Agent', 1);
    expect($c->fresh()->first_response_at)->not->toBeNull()
        ->and($c->messages()->count())->toBe(2)
        ->and($c->firstResponseMinutes())->toBeInt();

    $c->resolve();
    expect($c->fresh()->status)->toBe(ConversationStatus::Resolved)
        ->and($c->fresh()->resolved_at)->not->toBeNull();

    $c->reopen();
    expect($c->fresh()->resolved_at)->toBeNull()
        ->and($c->fresh()->status)->toBe(ConversationStatus::Open);

    $c->escalate();
    expect($c->fresh()->status)->toBe(ConversationStatus::Escalated)
        ->and($c->fresh()->escalated_at)->not->toBeNull();
});

it('re-opens an awaiting-customer thread when the customer replies', function () {
    $c = Conversation::query()->create(['tenant_id' => 'a', 'nature' => ConversationNature::Question, 'status' => ConversationStatus::AwaitingCustomer]);

    $c->postMessage(MessageAuthor::Customer, 'still stuck');

    expect($c->fresh()->status)->toBe(ConversationStatus::Open);
});

it('opens a conversation from the customer chat widget', function () {
    $tenant = Tenant::create(['id' => 'cs']);
    tenancy()->initialize($tenant);
    Filament::setCurrentPanel('app');

    $user = User::query()->create([
        'name' => 'Cust', 'email' => 'cust@cs.test',
        'password' => Hash::make('secret123'), 'role' => UserRole::Admin,
    ]);
    test()->actingAs($user);

    Livewire::test(SupportChatWidget::class)
        ->set('message', 'How do I settle an invoice?')
        ->call('send');

    $conversation = Conversation::query()->where('tenant_id', 'cs')->first();

    expect($conversation)->not->toBeNull()
        ->and($conversation->status)->toBe(ConversationStatus::New)
        ->and($conversation->requester_email)->toBe('cust@cs.test')
        ->and($conversation->sla_due_at)->not->toBeNull()
        ->and($conversation->messages()->count())->toBe(1);

    tenancy()->end();
    $tenant->delete();
});

it('confines the console + KPI widget to central staff', function () {
    $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner@ea.test', 'password' => Hash::make('secret123')]);
    test()->actingAs($owner);
    Filament::setCurrentPanel('admin');

    expect($owner->isCentralUser())->toBeTrue()
        ->and(ConversationResource::canAccess())->toBeTrue()
        ->and(CustomerServiceStats::canView())->toBeTrue();

    Livewire::test(CustomerServiceStats::class)->assertOk();
});

it('computes the median first-response KPI correctly', function () {
    // Three answered conversations: 10, 30 and 50 minutes → median 30.
    foreach ([10, 30, 50] as $i => $minutes) {
        Conversation::query()->create([
            'tenant_id' => 'k'.$i,
            'nature' => ConversationNature::Question,
            'status' => ConversationStatus::Resolved,
            'created_at' => now()->subDays(2),
            'first_response_at' => now()->subDays(2)->addMinutes($minutes),
        ]);
    }

    $owner = User::query()->create(['name' => 'O', 'email' => 'kpi@ea.test', 'password' => Hash::make('secret123')]);
    test()->actingAs($owner);
    Filament::setCurrentPanel('admin');

    Livewire::test(CustomerServiceStats::class)->assertSee('30 min');
});
