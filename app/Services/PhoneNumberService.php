<?php

namespace App\Services;

class PhoneNumberService
{
    /**
     * Normalizes a phone number to Tanzania's +255 country code format so that
     * wa.me / sms: links always resolve correctly. Accepts local (0712345678),
     * bare (712345678), or already-international (+255712345678 / 255712345678)
     * formats. Numbers that already carry a different country code are left as-is.
     */
    public function normalize(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }

        $digits = preg_replace('/[^\d+]/', '', trim($raw));

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '+255')) {
            return $digits;
        }

        if (str_starts_with($digits, '255')) {
            return '+'.$digits;
        }

        if (str_starts_with($digits, '0')) {
            return '+255'.substr($digits, 1);
        }

        if (str_starts_with($digits, '+')) {
            return $digits; // explicit foreign country code — leave untouched
        }

        if (preg_match('/^\d{9}$/', $digits)) {
            return '+255'.$digits; // bare 9-digit local number, no leading 0
        }

        return $digits;
    }

    /** Digits only (no leading +), as required by the wa.me URL scheme. */
    public function digitsOnly(?string $phone): ?string
    {
        $normalized = $this->normalize($phone);

        return $normalized ? ltrim(preg_replace('/\D/', '', $normalized), '0') : null;
    }
}
