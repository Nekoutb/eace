<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Emails a support agent's reply to a visitor who asked from the login-page chat —
 * they aren't signed in, so they can't see the reply in-app.
 */
class SupportReplyMail extends Mailable
{
    public function __construct(
        public string $body,
        public ?string $question = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: (string) __('support.reply_mail.subject'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.support-reply');
    }
}
