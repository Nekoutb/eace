<?php

declare(strict_types=1);

namespace App\Mail;

use App\Services\Registry\TaxpayerIntegrityReport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The post-refresh email: confirms the daily taxpayer-register download and carries
 * the data-integrity report so the operator can trust the data before it's relied on.
 */
class TaxpayerFetchReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly TaxpayerIntegrityReport $report) {}

    public function envelope(): Envelope
    {
        $tag = match (true) {
            $this->report->status === 'failed' => '❌ FAILED',
            $this->report->warnings() !== [] => '⚠️ check',
            $this->report->status === 'unchanged' => '✅ unchanged',
            default => '✅ OK',
        };

        return new Envelope(
            subject: 'Taxpayer register '.$tag.' — '.$this->report->period,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.taxpayer-fetch-report',
            with: ['r' => $this->report],
        );
    }
}
