<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Sent to a brand-new account with its username, temporary password and a sign-in link. */
class AccountCreatedMail extends Mailable
{
    public function __construct(public User $user, public string $plainPassword)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your '.config('app.name').' account is ready');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.account-created', with: [
            'name' => $this->user->name,
            'username' => $this->user->username,
            'password' => $this->plainPassword,
            'loginUrl' => route('login'),
            'appName' => config('app.name'),
        ]);
    }
}
