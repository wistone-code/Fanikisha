<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Plain-text emails (account requests, data requests and their replies).
 *
 * Railway's Hobby plan blocks outbound SMTP and the default mailer is "log", so a bare Mail::raw()
 * never reaches anyone in production. Like AccountMailer, this sends over Resend's HTTPS API when
 * RESEND_API_KEY is set, and falls back to the configured Laravel mailer otherwise.
 * Never throws: callers get true/false and the failure is logged as an error.
 */
class PlainMailer
{
    public function send(string $to, string $subject, string $text, ?string $replyTo = null, ?string $replyName = null): bool
    {
        if (blank($to)) {
            return false;
        }

        try {
            if (config('services.resend.key')) {
                $from = config('mail.from');

                $payload = [
                    'from' => $from['name'].' <'.$from['address'].'>',
                    'to' => [$to],
                    'subject' => $subject,
                    'text' => $text,
                ];

                if (filled($replyTo)) {
                    $payload['reply_to'] = [filled($replyName) ? $replyName.' <'.$replyTo.'>' : $replyTo];
                }

                Http::withToken(config('services.resend.key'))->timeout(10)->post('https://api.resend.com/emails', $payload)->throw();
            } else {
                Mail::raw($text, function ($m) use ($to, $subject, $replyTo, $replyName) {
                    $m->to($to)->subject($subject);
                    if (filled($replyTo)) {
                        $m->replyTo($replyTo, $replyName);
                    }
                });
            }

            return true;
        } catch (Throwable $e) {
            Log::error('Plain email failed: '.$e->getMessage(), ['to' => $to, 'subject' => $subject]);

            return false;
        }
    }
}
