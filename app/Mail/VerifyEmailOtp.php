<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerifyEmailOtp extends Mailable
{
    use Queueable, SerializesModels;

    public $otp;

    // On reçoit le code OTP lors de l'instanciation
    public function __construct($otp)
    {
        $this->otp = $otp;
    }

    // Définition de l'objet de l'e-mail
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Votre code de vérification - Buudi',
        );
    }

    // Lien avec la vue Blade que vous avez créée (resources/views/emails/verify_otp.blade.php)
    public function content(): Content
    {
        return new Content(
            view: 'emails.verify_otp',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}