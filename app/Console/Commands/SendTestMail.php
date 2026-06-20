<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends a one-line test email through the configured mailer — used to confirm the
 * SMTP credentials work end to end during deployment, before relying on the
 * onboarding and password-reset flows.
 */
class SendTestMail extends Command
{
    protected $signature = 'mail:test {email : Where to send the test message}';

    protected $description = 'Send a test email through the configured mailer to verify SMTP.';

    public function handle(): int
    {
        $to = (string) $this->argument('email');

        try {
            Mail::raw(
                'Elite Advisors Compliance Engine — SMTP test sent at '.now()->toIso8601String().'.',
                fn ($message) => $message->to($to)->subject('Elite Advisors — SMTP test'),
            );
        } catch (Throwable $e) {
            $this->error('Failed to send: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Test email sent to {$to} via mailer [".config('mail.default').'].');

        return self::SUCCESS;
    }
}
