<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent to a newly onboarded (or password-reset) Academy member with their /academy
 * sign-in URL and a one-time password. They are prompted to set their own password
 * on first sign-in (must_change_password), so its time in the inbox is bounded.
 */
class AcademyCredentialsMail extends Mailable
{
    public function __construct(
        public string $name,
        public string $email,
        public string $temporaryPassword,
        public string $loginUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: (string) __('academy.mail_subject'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.academy-credentials');
    }
}
