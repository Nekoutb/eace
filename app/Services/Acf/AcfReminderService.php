<?php

declare(strict_types=1);

namespace App\Services\Acf;

use App\Mail\AcfExpiryReminderMail;
use App\Models\AcfCertificate;
use App\Models\DataAccessLog;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the "your ACF is about to expire" reminder to a taxpayer on behalf of an
 * EACE user, then records that it happened (timestamp + count + access log).
 *
 * Kept out of the Filament action so it is unit-testable and reusable — a future
 * bulk "remind all expiring" or scheduled auto-reminder can call the same path.
 */
final class AcfReminderService
{
    public function send(AcfCertificate $certificate, string $email, ?string $note, User $sender): void
    {
        Mail::to($email)->send(new AcfExpiryReminderMail(
            taxpayerName: (string) $certificate->taxpayer_name,
            acfReference: $certificate->reference,
            expiresAt: $certificate->expires_at,
            daysLeft: $certificate->daysToExpiry(),
            senderName: $sender->name,
            senderEmail: $sender->email,
            note: filled($note) ? $note : null,
        ));

        $certificate->update([
            'reminded_at' => now(),
            'reminder_count' => (int) $certificate->reminder_count + 1,
        ]);

        DataAccessLog::record('send-reminder', 'acf-expiry', 'ACF '.$certificate->reference.' → '.$email);
    }
}
