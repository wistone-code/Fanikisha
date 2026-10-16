<?php

namespace App\Services;

use App\Models\User;
use Throwable;

/**
 * Texts a new account's sign-in link, username and temporary password. Bilingual so it fits in one SMS whatever the
 * person's language. Never throws: a failure just means "share the details yourself", like a failed email.
 */
class AccountSms
{
    public function __construct(private BeemSmsService $sms)
    {
    }

    public static function message(User $user, string $plainPassword): string
    {
        return 'Fanikisha: Ingia/Sign in '.url('/login').' Jina/User: '.$user->username.' Nenosiri/Password: '.$plainPassword.' Badilisha baada ya kuingia.';
    }

    /** @return array{sent: bool, error: ?string} */
    public function sendWelcome(User $user, string $plainPassword): array
    {
        if (blank($user->phone)) {
            return ['sent' => false, 'error' => 'No phone number was given.'];
        }

        try {
            $result = $this->sms->sendSingle(self::message($user, $plainPassword), $user->phone);
        } catch (Throwable $e) {
            report($e);

            return ['sent' => false, 'error' => 'The SMS service could not be reached.'];
        }

        return ($result['successful'] ?? false)
            ? ['sent' => true, 'error' => null]
            : ['sent' => false, 'error' => $result['error'] ?? 'The SMS could not be sent.'];
    }
}
