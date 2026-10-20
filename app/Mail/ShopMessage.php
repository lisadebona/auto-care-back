<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ShopMessage extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $messageSubject,
        public string $messageBody,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->messageSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.shop-message',
            with: [
                'body' => $this->messageBody,
            ],
        );
    }
}
