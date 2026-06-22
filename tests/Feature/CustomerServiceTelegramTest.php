<?php

declare(strict_types=1);

use App\Domain\Auth\StaffRole;
use App\Domain\Support\ConversationNature;
use App\Domain\Support\ConversationStatus;
use App\Domain\Support\MessageAuthor;
use App\Models\Conversation;
use App\Models\User;
use App\Services\Support\TelegramNotifier;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

it('pushes an escalated conversation to the team group and remembers the message', function () {
    config(['services.telegram.bot_token' => 'TESTTOKEN', 'services.telegram.chat_id' => '-100123']);
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 4242]])]);

    $c = Conversation::query()->create(['tenant_id' => 'a', 'tenant_name' => 'Acme', 'status' => ConversationStatus::Escalated, 'nature' => ConversationNature::Complaint]);
    $c->postMessage(MessageAuthor::Customer, 'Nothing works at all');

    app(TelegramNotifier::class)->pushEscalation($c);

    expect($c->fresh()->telegram_message_id)->toBe('4242');
    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/sendMessage') && $request['chat_id'] === '-100123');
});

it('does nothing when Telegram is not configured (dormant)', function () {
    config(['services.telegram.bot_token' => null]);
    Http::fake();

    $c = Conversation::query()->create(['tenant_id' => 'a', 'status' => ConversationStatus::Escalated, 'nature' => ConversationNature::Complaint]);
    app(TelegramNotifier::class)->pushEscalation($c);

    expect($c->fresh()->telegram_message_id)->toBeNull();
    Http::assertNothingSent();
});

it('rejects a webhook call without the matching secret', function () {
    config(['services.telegram.webhook_secret' => 's3cr3t']);

    test()->postJson('/telegram/webhook/wrong', ['message' => []])->assertForbidden();
    test()->postJson('/telegram/webhook/s3cr3t', ['message' => []])->assertForbidden(); // right path, missing header
});

it('routes a Telegram reply back into the conversation', function () {
    config(['services.telegram.webhook_secret' => 's3cr3t']);

    $c = Conversation::query()->create(['tenant_id' => 'a', 'status' => ConversationStatus::Escalated, 'nature' => ConversationNature::Complaint, 'telegram_message_id' => '4242']);
    $c->postMessage(MessageAuthor::Customer, 'help me');

    test()->withHeaders(['X-Telegram-Bot-Api-Secret-Token' => 's3cr3t'])
        ->postJson('/telegram/webhook/s3cr3t', [
            'message' => [
                'text' => 'We are on it — sorry for the trouble!',
                'reply_to_message' => ['message_id' => 4242],
                'from' => ['first_name' => 'Boma', 'username' => 'boma_ea', 'id' => 555],
            ],
        ])->assertOk();

    $c->refresh();
    expect($c->status)->toBe(ConversationStatus::AwaitingCustomer)
        ->and($c->messages()->where('author', 'agent')->where('body', 'We are on it — sorry for the trouble!')->exists())->toBeTrue();
});

it('auto-links a staff member chat_id the first time they message the bot', function () {
    config(['services.telegram.webhook_secret' => 's3cr3t']);

    $agent = User::query()->create([
        'name' => 'Boma', 'email' => 'boma@ea.test', 'password' => Hash::make('secret123'),
        'staff_role' => StaffRole::Agent, 'telegram_username' => 'boma_ea',
    ]);

    test()->withHeaders(['X-Telegram-Bot-Api-Secret-Token' => 's3cr3t'])
        ->postJson('/telegram/webhook/s3cr3t', [
            'message' => ['text' => '/start', 'from' => ['username' => 'Boma_EA', 'id' => 999, 'first_name' => 'Boma']],
        ])->assertOk();

    expect($agent->fresh()->telegram_chat_id)->toBe('999');
});

it('routes a plain group message (no reply) to the most recent open escalation', function () {
    config(['services.telegram.webhook_secret' => 's3cr3t']);

    Conversation::query()->create(['tenant_id' => 'old', 'status' => ConversationStatus::Escalated, 'telegram_message_id' => '10', 'last_activity_at' => now()->subHour()]);
    $newest = Conversation::query()->create(['tenant_id' => 'new', 'status' => ConversationStatus::Escalated, 'telegram_message_id' => '11', 'last_activity_at' => now()]);

    test()->withHeaders(['X-Telegram-Bot-Api-Secret-Token' => 's3cr3t'])
        ->postJson('/telegram/webhook/s3cr3t', [
            'message' => ['text' => 'Here is your answer', 'from' => ['first_name' => 'Rep', 'is_bot' => false]],
        ])->assertOk();

    expect($newest->fresh()->messages()->where('author', 'agent')->where('body', 'Here is your answer')->exists())->toBeTrue()
        ->and($newest->fresh()->status)->toBe(ConversationStatus::AwaitingCustomer);
});

it('ignores the bot own posts and slash-commands in the group', function () {
    config(['services.telegram.webhook_secret' => 's3cr3t']);
    $c = Conversation::query()->create(['tenant_id' => 'a', 'status' => ConversationStatus::Escalated, 'telegram_message_id' => '20', 'last_activity_at' => now()]);

    test()->withHeaders(['X-Telegram-Bot-Api-Secret-Token' => 's3cr3t'])
        ->postJson('/telegram/webhook/s3cr3t', ['message' => ['text' => 'Reply to this message…', 'from' => ['is_bot' => true, 'first_name' => 'Bot']]])->assertOk();
    test()->withHeaders(['X-Telegram-Bot-Api-Secret-Token' => 's3cr3t'])
        ->postJson('/telegram/webhook/s3cr3t', ['message' => ['text' => '/help', 'from' => ['first_name' => 'Rep']]])->assertOk();

    expect($c->fresh()->messages()->where('author', 'agent')->count())->toBe(0);
});
