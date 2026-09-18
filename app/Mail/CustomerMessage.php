<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class CustomerMessage extends Mailable
{
    public function __construct(public string $heading, public string $bodyText, public ?string $code = null, public ?string $orderNumber = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->code !== null ? 'Votre code de confirmation Madina Import' : $this->heading.' — Madina Import');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.customer', text: 'emails.customer-text');
    }
}
