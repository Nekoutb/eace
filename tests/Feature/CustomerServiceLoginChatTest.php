<?php

declare(strict_types=1);

use App\Domain\Support\ConversationNature;
use App\Domain\Support\ConversationStatus;
use App\Mail\SupportReplyMail;
use App\Models\Conversation;
use App\Services\Support\BotReply;
use App\Services\Support\SupportBotConnector;
use Illuminate\Support\Facades\Mail;

/** Bind a deterministic bot (bindBot() also exists in the bot test; redefined locally for clarity). */
function bindLoginBot(BotReply $reply): void
{
    app()->instance(SupportBotConnector::class, new class($reply) implements SupportBotConnector
    {
        public function __construct(private BotReply $reply) {}

        public function respond(string $question, string $workspace, string $locale): BotReply
        {
            return $this->reply;
        }
    });
}

it('answers a login-chat question with the bot', function () {
    bindLoginBot(new BotReply('Click "Forgot password" on the sign-in page.', true, ConversationNature::HowTo, false));

    $res = test()->postJson('/help/ask', ['message' => 'How do I reset my password?'])->assertOk();

    $res->assertJson(['escalated' => false]);
    expect($res->json('reply'))->toContain('Forgot password');

    $c = Conversation::query()->where('channel', 'login')->first();
    expect($c)->not->toBeNull()
        ->and($c->bot_resolved)->toBeTrue()
        ->and($c->tenant_id)->toBeNull();
});

it('escalates a login-chat inquiry the bot cannot resolve and keeps the email', function () {
    bindLoginBot(new BotReply('A specialist will follow up.', false, ConversationNature::Billing, false));

    test()->postJson('/help/ask', ['message' => 'How much does it cost?', 'email' => 'Prospect@X.com'])
        ->assertOk()
        ->assertJson(['escalated' => true]);

    $c = Conversation::query()->where('channel', 'login')->first();
    expect($c->status)->toBe(ConversationStatus::Escalated)
        ->and($c->requester_email)->toBe('prospect@x.com'); // normalised
});

it('rejects an empty login-chat message', function () {
    test()->postJson('/help/ask', ['message' => ''])->assertInvalid('message');

    expect(\App\Models\Conversation::query()->count())->toBe(0);
});

it('emails the visitor when an agent replies to a login inquiry', function () {
    Mail::fake();
    $c = Conversation::query()->create(['channel' => 'login', 'requester_email' => 'v@x.com', 'status' => ConversationStatus::Escalated, 'nature' => ConversationNature::Question]);

    $c->emailRequesterReply('Here is the pricing information you asked for.');

    Mail::assertSent(SupportReplyMail::class, fn (SupportReplyMail $m): bool => $m->hasTo('v@x.com'));
});

it('does not email a signed-in customer (app channel) on reply', function () {
    Mail::fake();
    $c = Conversation::query()->create(['channel' => 'app', 'requester_email' => 'c@x.com', 'status' => ConversationStatus::Open, 'nature' => ConversationNature::Question]);

    $c->emailRequesterReply('Reply');

    Mail::assertNothingSent();
});
