<?php

declare(strict_types=1);

namespace App\Mail;

use Carbon\CarbonInterface;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent to a taxpayer (counterparty) from the Certificates screen to remind them
 * that their fiscal-clearance certificate (ACF) is about to expire.
 *
 * It leaves from the platform's authorised mailbox so it actually delivers
 * (the shared SMTP account can only legitimately send as itself), but carries
 * the acting EACE user's name on the From and their address as Reply-To, so the
 * taxpayer's reply reaches that user directly. The body is always bilingual
 * (French then English), so it is composed inline rather than per-locale.
 */
class AcfExpiryReminderMail extends Mailable
{
    public function __construct(
        public string $taxpayerName,
        public string $acfReference,
        public CarbonInterface $expiresAt,
        public int $daysLeft,
        public string $senderName,
        public string $senderEmail,
        public ?string $note = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address((string) config('mail.from.address'), $this->senderName.' · Elite Advisors'),
            replyTo: [new Address($this->senderEmail, $this->senderName)],
            subject: 'Votre Attestation de Conformité Fiscale arrive à échéance · Your tax clearance certificate is expiring',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.acf-expiry-reminder');
    }
}
