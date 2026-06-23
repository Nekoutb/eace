<?php

declare(strict_types=1);

use App\Domain\Acf\AcfStatus;
use App\Domain\Auth\UserRole;
use App\Mail\AcfExpiryReminderMail;
use App\Models\AcfCertificate;
use App\Models\DataAccessLog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Acf\AcfReminderService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->tenant = Tenant::create(['id' => 'acfrem']);
    tenancy()->initialize($this->tenant);
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

function reminderSender(): User
{
    return User::query()->create([
        'name' => 'Agent Jean',
        'email' => 'jean@acme.test',
        'password' => Hash::make('secret123'),
        'role' => UserRole::Admin,
    ]);
}

function reminderCert(array $overrides = []): AcfCertificate
{
    return AcfCertificate::query()->create(array_merge([
        'reference' => '620123456789',
        'niu' => 'M010000009392X',
        'taxpayer_name' => 'STE ACME SARL',
        'issued_at' => CarbonImmutable::now()->subMonths(2),
        'expires_at' => CarbonImmutable::now()->addDays(8),
        'validity_months' => 3,
        'status' => AcfStatus::ExpiringSoon,
    ], $overrides));
}

it('emails the taxpayer, records the reminder, and audit-logs it', function () {
    Mail::fake();
    $sender = reminderSender();
    test()->actingAs($sender);
    $cert = reminderCert();

    app(AcfReminderService::class)->send($cert, 'taxpayer@vendor.cm', 'Merci de renouveler.', $sender);

    Mail::assertSent(AcfExpiryReminderMail::class, fn (AcfExpiryReminderMail $mail): bool => $mail->hasTo('taxpayer@vendor.cm')
        && $mail->senderEmail === $sender->email
        && $mail->acfReference === '620123456789'
        && $mail->note === 'Merci de renouveler.');

    $cert->refresh();
    expect($cert->reminder_count)->toBe(1)
        ->and($cert->reminded_at)->not->toBeNull()
        ->and(DataAccessLog::query()->where('action', 'send-reminder')->where('resource', 'acf-expiry')->exists())->toBeTrue();
});

it('bumps the reminder count on a second send', function () {
    Mail::fake();
    $sender = reminderSender();
    test()->actingAs($sender);
    $cert = reminderCert();

    app(AcfReminderService::class)->send($cert, 'taxpayer@vendor.cm', null, $sender);
    app(AcfReminderService::class)->send($cert, 'taxpayer@vendor.cm', null, $sender);

    expect($cert->refresh()->reminder_count)->toBe(2);
});

it('builds the reminder with a platform From, the user as Reply-To, and a bilingual body', function () {
    $mail = new AcfExpiryReminderMail(
        taxpayerName: 'STE ACME SARL',
        acfReference: '620123456789',
        expiresAt: CarbonImmutable::parse('2026-07-12'),
        daysLeft: 8,
        senderName: 'Agent Jean',
        senderEmail: 'jean@acme.test',
        note: null,
    );

    $envelope = $mail->envelope();
    expect($envelope->from?->address)->toBe((string) config('mail.from.address'))
        ->and($envelope->replyTo[0]->address)->toBe('jean@acme.test');

    $html = $mail->render();
    expect($html)->toContain('620123456789')   // the ACF reference
        ->toContain('12/07/2026')               // the expiry date
        ->toContain('Français')                 // both language blocks present
        ->toContain('English')
        ->toContain('jean@acme.test');          // the sender signature
});
