<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Carries the 6-digit password recovery code. */
class PasswordResetCodeMail extends Mailable
{
    public function __construct(public User $user, public string $code, public int $minutes)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: config('app.name').' password reset code');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.password-reset-code', with: [
            'name' => $this->user->name,
            'code' => $this->code,
            'minutes' => $this->minutes,
            'appName' => config('app.name'),
        ]);
    }
}
