<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent to a prospective customer to confirm their email before their workspace is
 * created (self-service onboarding, step 1 → 2). Sent synchronously so the link
 * arrives while they are still on the "check your email" screen.
 */
class WorkspaceVerifyMail extends Mailable
{
    public function __construct(
        public string $name,
        public string $orgName,
        public string $verifyUrl,
        public int $expiryHours,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: (string) __('app.mail.verify.subject'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.verify');
    }
}
