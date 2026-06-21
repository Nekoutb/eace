<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Invites a workspace's users to the anonymous customer-experience survey. The
 * link is workspace-scoped only — responses carry no individual identity.
 */
class SurveyInviteMail extends Mailable
{
    public function __construct(
        public string $name,
        public string $orgName,
        public string $surveyUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: (string) __('support.survey.subject'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.survey-invite');
    }
}
