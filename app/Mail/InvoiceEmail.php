<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to a contact when a business emails them their invoice pay link.
 * The client can open the link, review the line items, and pay online via Stripe.
 */
final class InvoiceEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public readonly string $businessName;
    public readonly string $payUrl;

    public function __construct(public readonly Invoice $invoice)
    {
        $account = $invoice->account;
        $this->businessName = $account?->site?->business_name ?: ($account?->name ?? config('app.name'));
        $this->payUrl = $invoice->publicUrl();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Invoice ' . $this->invoice->number . ' from ' . $this->businessName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.invoice',
        );
    }
}
