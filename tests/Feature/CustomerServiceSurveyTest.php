<?php

declare(strict_types=1);

use App\Domain\Auth\UserRole;
use App\Domain\Support\ConversationNature;
use App\Domain\Support\ConversationStatus;
use App\Filament\Widgets\CustomerServiceStats;
use App\Livewire\SupportChat;
use App\Mail\SurveyInviteMail;
use App\Models\Conversation;
use App\Models\SurveyResponse;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

it('stores an anonymous survey response tagged by workspace only', function () {
    $token = Crypt::encrypt(['t' => 'dhl', 'n' => 'DHL Cameroon']);

    test()->get('/survey?k='.urlencode($token))->assertOk();

    test()->post('/survey', [
        'k' => $token,
        'service_quality' => 5,
        'ease_of_use' => 4,
        'responsiveness' => 5,
        'recommend' => 9,
        'comment' => 'Great tool',
    ])->assertOk();

    $r = SurveyResponse::query()->first();

    expect($r)->not->toBeNull()
        ->and($r->tenant_id)->toBe('dhl')
        ->and($r->tenant_name)->toBe('DHL Cameroon')
        ->and($r->service_quality)->toBe(5)
        ->and($r->recommend)->toBe(9)
        ->and($r->comment)->toBe('Great tool');
});

it('404s an invalid or missing survey token', function () {
    test()->get('/survey?k=not-a-real-token')->assertNotFound();
    test()->get('/survey')->assertNotFound();
});

it('computes NPS as promoters minus detractors', function () {
    // recommends 10,9 (2 promoters), 8,7 (passives), 3 (1 detractor) → (2−1)/5 = +20
    foreach ([10, 9, 8, 7, 3] as $score) {
        SurveyResponse::query()->create(['tenant_id' => 't', 'recommend' => $score, 'submitted_at' => now()]);
    }

    expect(SurveyResponse::nps(SurveyResponse::all()))->toBe(20);
});

it('records post-resolution CSAT from the chat widget', function () {
    config(['niu.support.bot_enabled' => false]);
    $tenant = Tenant::create(['id' => 'csat']);
    tenancy()->initialize($tenant);
    Filament::setCurrentPanel('app');
    $user = User::query()->create(['name' => 'C', 'email' => 'c@csat.test', 'password' => Hash::make('secret123'), 'role' => UserRole::Admin]);
    test()->actingAs($user);

    $c = Conversation::query()->create(['tenant_id' => 'csat', 'requester_email' => 'c@csat.test', 'status' => ConversationStatus::Resolved, 'nature' => ConversationNature::Question]);

    Livewire::test(SupportChat::class)->call('rate', $c->id, 1);

    expect($c->fresh()->csat_score)->toBe(1);

    tenancy()->end();
    $tenant->delete();
});

it('shows real CSAT and NPS on the dashboard', function () {
    Conversation::query()->create(['tenant_id' => 'a', 'status' => ConversationStatus::Resolved, 'nature' => ConversationNature::Question, 'csat_score' => 1, 'created_at' => now()->subDay()]);
    Conversation::query()->create(['tenant_id' => 'b', 'status' => ConversationStatus::Resolved, 'nature' => ConversationNature::Question, 'csat_score' => 0, 'created_at' => now()->subDay()]);
    SurveyResponse::query()->create(['tenant_id' => 'a', 'recommend' => 10, 'submitted_at' => now()]);
    SurveyResponse::query()->create(['tenant_id' => 'b', 'recommend' => 9, 'submitted_at' => now()]);

    $owner = User::query()->create(['name' => 'O', 'email' => 'srvkpi@ea.test', 'password' => Hash::make('secret123')]);
    test()->actingAs($owner);
    Filament::setCurrentPanel('admin');

    Livewire::test(CustomerServiceStats::class)
        ->assertSee('50%')   // CSAT: 1 of 2 satisfied
        ->assertSee('100');  // NPS: 2 promoters
});

it('emails the anonymous survey to workspace admins', function () {
    Mail::fake();
    $tenant = Tenant::create(['id' => 'srv', 'name' => 'Survey Co']);
    tenancy()->initialize($tenant);
    User::query()->create(['name' => 'Admin', 'email' => 'admin@srv.test', 'password' => Hash::make('secret123'), 'role' => UserRole::Admin]);
    tenancy()->end();

    test()->artisan('survey:send', ['--tenant' => 'srv'])->assertExitCode(0);

    Mail::assertSent(SurveyInviteMail::class, fn (SurveyInviteMail $m): bool => $m->hasTo('admin@srv.test'));

    $tenant->delete();
});
