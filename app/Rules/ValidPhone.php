<?php

namespace App\Rules;

use App\Services\PhoneNumberService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** A phone number the app can actually use: 9 digits or more once tidied up (0712 345 678, +255712345678, ...). */
class ValidPhone implements ValidationRule
{
    public static function ok(?string $value): bool
    {
        $normalized = app(PhoneNumberService::class)->normalize($value);

        return $normalized !== null && strlen(preg_replace('/\D/', '', $normalized)) >= 9;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::ok((string) $value)) {
            $fail('Please enter a valid phone number, for example 0712 345 678.');
        }
    }
}
