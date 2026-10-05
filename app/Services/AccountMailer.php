<?php

namespace App\Services;

use App\Mail\AccountCreatedMail;
use App\Mail\PasswordResetCodeMail;
use App\Models\User;
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
            Mail::to($user->email)->send($mailable);

            return true;
        } catch (Throwable $e) {
            Log::error('Account email failed: '.$e->getMessage(), ['user_id' => $user->id]);

            return false;
        }
    }
}
