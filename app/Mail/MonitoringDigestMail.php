<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The daily operations digest: services that are overdue, renewing soon, or whose
 * site is down. Sent only when there is something to report.
 */
class MonitoringDigestMail extends Mailable
{
    /**
     * @param  list<array{name: string, detail: string}>  $overdue
     * @param  list<array{name: string, detail: string}>  $dueSoon
     * @param  list<array{name: string, detail: string}>  $down
     */
    public function __construct(
        public array $overdue,
        public array $dueSoon,
        public array $down,
    ) {}

    public function envelope(): Envelope
    {
        $count = count($this->overdue) + count($this->dueSoon) + count($this->down);

        return new Envelope(subject: (string) __('monitoring.mail_subject', ['n' => $count]));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.monitoring-digest');
    }
}
