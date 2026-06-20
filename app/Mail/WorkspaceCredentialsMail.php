<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent to a newly provisioned workspace administrator with their sign-in URL and
 * one-time password. Used by BOTH self-service onboarding (after email
 * confirmation) and admin onboarding. The password must be changed on first
 * sign-in (must_change_password), so its time in the inbox is bounded.
 */
class WorkspaceCredentialsMail extends Mailable
{
    public function __construct(
        public string $name,
        public string $orgName,
        public string $email,
        public string $temporaryPassword,
        public string $loginUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: (string) __('app.mail.credentials.subject'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.credentials');
    }
}
