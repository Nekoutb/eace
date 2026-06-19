<?php

declare(strict_types=1);

use App\Domain\Auth\UserRole;
use App\Filament\App\Widgets\SupportChatWidget;
use App\Filament\Resources\SupportQueries\SupportQueryResource;
use App\Models\SupportQuery;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    SupportQuery::query()->delete();
    $this->tenant = Tenant::create(['id' => 'chat', 'name' => 'Chat Co']);
    tenancy()->initialize($this->tenant);
    Filament::setCurrentPanel('app');
    $this->user = User::query()->create([
        'name' => 'Asker',
        'email' => 'asker@chat.test',
        'password' => bcrypt('secret123'),
        'role' => UserRole::Recorder,
    ]);
    test()->actingAs($this->user);
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
    SupportQuery::query()->delete();
});

it('stores a submitted question centrally, tagged with the tenant and the user', function () {
    Livewire::test(SupportChatWidget::class)
        ->set('message', 'How do I record an invoice?')
        ->call('send')
        ->assertSet('message', null);

    $query = SupportQuery::query()->latest('id')->first();

    expect($query)->not->toBeNull()
        ->and($query->tenant_id)->toBe('chat')
        ->and($query->tenant_name)->toBe('Chat Co')
        ->and($query->user_email)->toBe('asker@chat.test')
        ->and($query->message)->toBe('How do I record an invoice?')
        ->and($query->status)->toBe('new');
});

it('shows the user only their own questions, with the reply', function () {
    SupportQuery::query()->create([
        'tenant_id' => 'chat', 'user_email' => 'asker@chat.test',
        'message' => 'Question A', 'response' => 'Here is the answer', 'status' => 'answered',
    ]);
    // Another user's question in another workspace must not leak through.
    SupportQuery::query()->create([
        'tenant_id' => 'other', 'user_email' => 'someone@else.test',
        'message' => 'Secret', 'status' => 'new',
    ]);

    $queries = Livewire::test(SupportChatWidget::class)->instance()->recentQueries();

    expect($queries->pluck('message')->all())->toBe(['Question A'])
        ->and($queries->first()->response)->toBe('Here is the answer');
});

it('hides the support inbox from tenant users and badges the backlog for the owner', function () {
    // A tenant (non-central) user cannot reach the central support inbox.
    expect(SupportQueryResource::canAccess())->toBeFalse();

    SupportQuery::query()->create(['tenant_id' => 'chat', 'message' => 'x', 'status' => 'new']);
    SupportQuery::query()->create(['tenant_id' => 'chat', 'message' => 'y', 'status' => 'answered']);

    // The nav badge counts only the unanswered questions.
    expect(SupportQueryResource::getNavigationBadge())->toBe('1');
});
