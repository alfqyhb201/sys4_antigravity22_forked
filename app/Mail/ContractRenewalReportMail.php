<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContractRenewalReportMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @param  array<int, array<string, mixed>>  $renewedContracts
     * @param  array<int, array<string, mixed>>  $expiredContracts
     * @param  array<int, array<string, mixed>>  $notifiedContracts
     * @param  array<int, array<string, mixed>>  $failedOperations
     * @param  array<string, mixed>  $summaryStats
     */
    public function __construct(
        public array $renewedContracts = [],
        public array $expiredContracts = [],
        public array $notifiedContracts = [],
        public array $failedOperations = [],
        public array $summaryStats = [],
        public ?string $reportTitle = null
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->reportTitle ?? '📊 تقرير دورة تجديد الاشتراكات وإصدار الفواتير - TrueERP',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.contract-renewal-report',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
