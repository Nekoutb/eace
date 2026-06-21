<?php

declare(strict_types=1);

use App\Domain\Auth\UserRole;
use App\Domain\Support\ConversationStatus;
use App\Domain\Support\MessageAuthor;
use App\Filament\Resources\Conversations\ConversationResource;
use App\Livewire\SupportChat;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    config(['niu.support.bot_enabled' => false]); // isolate the widget from the bot (tested separately)
    Conversation::query()->delete();
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
    Conversation::query()->delete();
});

it('opens a conversation centrally, tagged with the tenant and the user', function () {
    Livewire::test(SupportChat::class)
        ->set('message', 'How do I record an invoice?')
        ->call('send')
        ->assertSet('message', null);

    $conversation = Conversation::query()->latest('id')->first();

    expect($conversation)->not->toBeNull()
        ->and($conversation->tenant_id)->toBe('chat')
        ->and($conversation->tenant_name)->toBe('Chat Co')
        ->and($conversation->requester_email)->toBe('asker@chat.test')
        ->and($conversation->status)->toBe(ConversationStatus::New)
        ->and($conversation->messages->first()->body)->toBe('How do I record an invoice?');
});

it('continues one conversation across messages (multi-turn)', function () {
    $chat = Livewire::test(SupportChat::class);
    $chat->set('message', 'How do I record an invoice?')->call('send');
    $chat->set('message', 'And how do I find it later?')->call('send');

    $conversations = Conversation::query()->where('tenant_id', 'chat')->get();

    expect($conversations)->toHaveCount(1)
        ->and($conversations->first()->messages()->count())->toBe(2); // both customer turns, one thread
});

it('shows the user only their own conversations, with the reply', function () {
    $mine = Conversation::query()->create([
        'tenant_id' => 'chat', 'requester_email' => 'asker@chat.test',
        'subject' => 'Question A', 'status' => ConversationStatus::AwaitingCustomer,
    ]);
    $mine->postMessage(MessageAuthor::Customer, 'Question A', 'Asker');
    $mine->postMessage(MessageAuthor::Agent, 'Here is the answer', 'Agent', 1);

    // Another user's conversation in another workspace must not leak through.
    Conversation::query()->create([
        'tenant_id' => 'other', 'requester_email' => 'someone@else.test',
        'subject' => 'Secret', 'status' => ConversationStatus::New,
    ]);

    $conversations = Livewire::test(SupportChat::class)->instance()->recentConversations();

    expect($conversations->pluck('subject')->all())->toBe(['Question A']);

    $reply = $conversations->first()->messages->last(fn ($m) => $m->author->isStaffSide());
    expect($reply->body)->toBe('Here is the answer');
});

it('hides the support console from tenant users and badges the new backlog for staff', function () {
    expect(ConversationResource::canAccess())->toBeFalse(); // the signed-in user is a tenant user

    Conversation::query()->create(['tenant_id' => 'chat', 'subject' => 'x', 'status' => ConversationStatus::New]);
    Conversation::query()->create(['tenant_id' => 'chat', 'subject' => 'y', 'status' => ConversationStatus::Resolved]);

    expect(ConversationResource::getNavigationBadge())->toBe('1');
});
