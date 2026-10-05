<?php

namespace App\Services;

use App\Mail\AccountCreatedMail;
use App\Mail\PasswordResetCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Single place that sends account emails. Never throws: a mail outage must not
 * break account creation or crash the page, so callers get a boolean instead.
 */
class AccountMailer
{
    public function sendWelcome(User $user, string $plainPassword): bool
    {
        return $this->send($user, new AccountCreatedMail($user, $plainPassword));
    }

    public function sendResetCode(User $user, string $code, int $minutes): bool
    {
        return $this->send($user, new PasswordResetCodeMail($user, $code, $minutes));
    }

    private function send(User $user, $mailable): bool
    {
        if (blank($user->email)) {
            return false;
        }

        try {
            // Railway's Hobby plan blocks outbound SMTP, so when a Resend API key is
            // configured we send over HTTPS instead. Without a key we fall back to
            // whatever Laravel mailer is configured (SMTP, log, ...).
            if (config('services.resend.key')) {
                $this->sendViaResend($user, $mailable);
            } else {
                Mail::to($user->email)->send($mailable);
            }

            return true;
        } catch (Throwable $e) {
            Log::error('Account email failed: '.$e->getMessage(), ['user_id' => $user->id]);

            return false;
        }
    }

    private function sendViaResend(User $user, $mailable): void
    {
        $from = config('mail.from');

        Http::withToken(config('services.resend.key'))
            ->timeout(10)
            ->post('https://api.resend.com/emails', [
                'from' => $from['name'].' <'.$from['address'].'>',
                'to' => [$user->email],
                'subject' => $mailable->envelope()->subject,
                'html' => $mailable->render(),
            ])
            ->throw();
    }
}
