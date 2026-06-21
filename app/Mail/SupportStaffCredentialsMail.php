<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent to a newly onboarded customer-service staff member with their admin sign-in
 * URL and one-time password. The password must be changed on first sign-in
 * (must_change_password) and mandatory 2FA is enrolled then too.
 */
class SupportStaffCredentialsMail extends Mailable
{
    public function __construct(
        public string $name,
        public string $email,
        public string $temporaryPassword,
        public string $loginUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: (string) __('support.mail.subject'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.support-credentials');
    }
}
