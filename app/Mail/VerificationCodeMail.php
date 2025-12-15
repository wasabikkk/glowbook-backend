<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $code;

    /**
     * Create a new message instance.
     */
    public function __construct(string $code)
    {
        $this->code = $code;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Email Verification Code',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // Use FRONTEND_URL from .env; default to localhost if not set
        $frontendUrl = rtrim(env('FRONTEND_URL', 'http://localhost:3000'), '/');
        $verifyUrl = $frontendUrl . '/verify-email.html?code=' . urlencode($this->code);

        return new Content(
            view: 'emails.verification-code-html',
            text: 'emails.verification-code',
            with: [
                'code' => $this->code,
                'verifyUrl' => $verifyUrl,
            ],
        );
    }
}
