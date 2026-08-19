<?php

namespace App\Services;

class PasswordGeneratorService
{
    /** Unambiguous character set (no 0/O, 1/l/I) for system-generated temporary passwords. */
    private const CHARS = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';

    public function generate(int $length = 10): string
    {
        $chars = self::CHARS;
        $max = strlen($chars) - 1;
        $out = '';

        for ($i = 0; $i < $length; $i++) {
            $out .= $chars[random_int(0, $max)];
        }

        return $out;
    }

    public function generateSixDigitCode(): string
    {
        return (string) random_int(100000, 999999);
    }
}
