<?php

namespace App\Services;

use App\Models\User;

/**
 * Finds accounts that already use a given username, email or phone number.
 * Comparison is case-insensitive for username/email, and phone numbers are
 * compared after normalisation so 0712 345 678, 712345678 and +255712345678
 * all count as the same number.
 */
class AccountUniquenessService
{
    public function __construct(private PhoneNumberService $phones)
    {
    }

    /**
     * @return string[] human-readable warnings, empty when everything is unique
     */
    public function conflicts(?string $username, ?string $email, ?string $phone, ?int $ignoreUserId = null): array
    {
        $warnings = [];

        $base = fn () => User::query()->when($ignoreUserId, fn ($q) => $q->where('id', '!=', $ignoreUserId));

        if (filled($username) && $base()->whereRaw('LOWER(username) = ?', [mb_strtolower(trim($username))])->exists()) {
            $warnings[] = "The username \"{$username}\" is already in use by another account.";
        }

        if (filled($email) && $base()->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])->exists()) {
            $warnings[] = "The email \"{$email}\" is already in use by another account.";
        }

        if (filled($phone)) {
            $wanted = $this->phones->normalize($phone);

            $taken = $base()->whereNotNull('phone')->pluck('phone')
                ->contains(fn ($existing) => $this->phones->normalize($existing) === $wanted);

            if ($taken) {
                $warnings[] = "The phone number \"{$phone}\" is already in use by another account.";
            }
        }

        return $warnings;
    }
}
