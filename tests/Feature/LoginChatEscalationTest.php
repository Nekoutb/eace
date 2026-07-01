<?php

declare(strict_types=1);

use App\Domain\Support\ConversationNature;
use App\Domain\Support\ConversationStatus;
use App\Domain\Support\MessageAuthor;
use App\Mail\SupportReplyMail;
use App\Models\Conversation;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;

/*
 * The login-page "Need help?" chat for anonymous visitors. Because a visitor is not
 * signed in and the chat is one-shot, the ONLY way to reach them after an escalation
 * is the contact they leave — so escalation is gated on an email or phone, replies
 * are emailed, and a waiting visitor can poll for the team's answer.
 */

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class); // the chat routes are throttled
});

it('does not escalate a login-chat visitor until they leave contact details', function () {
    $response = $this->postJson(route('help.ask'), ['message' => 'I want to speak to a manager']);

    $response->assertOk()->assertJson(['escalated' => false, 'needContact' => true]);

    expect(Conversation::query()->where('channel', 'login')->where('status', ConversationStatus::Escalated->value)->count())->toBe(0);
});

it('escalates once the visitor provides an email', function () {
    $this->postJson(route('help.ask'), ['message' => 'I need a human']);                              // prompted for contact
    $response = $this->postJson(route('help.ask'), ['message' => 'please help', 'email' => 'visitor@example.com']);

    $response->assertOk()->assertJson(['escalated' => true]);

    $conversation = Conversation::query()->where('channel', 'login')->latest('id')->firstOrFail();
    expect($conversation->status)->toBe(ConversationStatus::Escalated)
        ->and($conversation->requester_email)->toBe('visitor@example.com');
});

it('escalates on a phone number alone (no email)', function () {
    $this->postJson(route('help.ask'), ['message' => 'beyond the bot please']);
    $response = $this->postJson(route('help.ask'), ['message' => 'reach me here', 'phone' => '+237675153953']);

    $response->assertOk()->assertJson(['escalated' => true]);

    $conversation = Conversation::query()->where('channel', 'login')->latest('id')->firstOrFail();
    expect($conversation->status)->toBe(ConversationStatus::Escalated)
        ->and($conversation->requester_phone)->toBe('+237675153953');
});

it('polls only the team replies for the visitor session', function () {
    $this->postJson(route('help.ask'), ['message' => 'hello', 'email' => 'v@example.com']);
    $conversation = Conversation::query()->where('channel', 'login')->latest('id')->firstOrFail();

    $conversation->postMessage(MessageAuthor::Agent, 'Hi, this is the team.', 'Agent');

    $messages = $this->getJson(route('help.poll').'?since=0')->assertOk()->json('messages');

    expect($messages)->toHaveCount(1)
        ->and($messages[0]['body'])->toBe('Hi, this is the team.');
});

it('emails a team reply to a login-chat visitor who left an email', function () {
    Mail::fake();

    $conversation = Conversation::query()->create([
        'channel' => 'login',
        'requester_email' => 'visitor@example.com',
        'subject' => 'Question',
        'nature' => ConversationNature::Question,
        'status' => ConversationStatus::Escalated,
        'last_activity_at' => now(),
    ]);

    $conversation->emailRequesterReply('Here is our answer.');

    Mail::assertSent(SupportReplyMail::class, fn (SupportReplyMail $mail): bool => $mail->hasTo('visitor@example.com'));
});
