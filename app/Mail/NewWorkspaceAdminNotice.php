<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Optional operator-visibility notice: a customer has just self-provisioned a
 * workspace. Informational only — no action required. Sent only when
 * config('niu.signup_admin_notify') is set.
 */
class NewWorkspaceAdminNotice extends Mailable
{
    public function __construct(
        public string $orgName,
        public string $email,
        public string $tenantId,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: (string) __('app.mail.admin_notice.subject', ['org' => $this->orgName]));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.admin-notice');
    }
}
