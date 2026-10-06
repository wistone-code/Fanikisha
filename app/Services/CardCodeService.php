<?php

namespace App\Services;

use App\Models\Event;

/**
 * Short card codes (e.g. "K7M2Q") so a guest whose phone is dead can still be found at
 * the door: staff type the code instead of scanning. Letters that look alike (O/0, I/1/L, S/5, B/8) are left out.
 */
class CardCodeService
{
    private const ALPHABET = 'ACDEFGHJKMNPQRTUVWXY2346789';

    public static function randomCode(int $length = 5): string
    {
        $code = '';
        $max = strlen(self::ALPHABET) - 1;

        for ($i = 0; $i < $length; $i++) {
            $code .= self::ALPHABET[random_int(0, $max)];
        }

        return $code;
    }

    /** A code not yet used in this event. */
    public function uniqueFor(int $eventId): string
    {
        do {
            $code = self::randomCode();
            $taken = \App\Models\Pledge::where('event_id', $eventId)->where('card_code', $code)->exists();
        } while ($taken);

        return $code;
    }

    /** Forgiving input: "k7m-2q", " k7m2q " and "K7M2Q" are the same code. */
    public static function normalize(string $input): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $input) ?? '');
    }
}
