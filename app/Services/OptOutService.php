<?php

namespace App\Services;

use App\Models\MessageOptOut;
use Illuminate\Http\RedirectResponse;

/**
 * The suppression list promised in the Privacy Policy and Acceptable Use Policy:
 * a number that asked to stop is never messaged again by us, for any event.
 */
class OptOutService
{
    public function __construct(private PhoneNumberService $phones)
    {
    }

    /** Digits-only international key, or null when the number is unusable. */
    public function key(?string $phone): ?string
    {
        $digits = $this->phones->digitsOnly($phone);

        return $digits && strlen($digits) >= 9 ? $digits : null;
    }

    public function add(?string $phone, string $source, ?int $eventId = null): bool
    {
        $key = $this->key($phone);

        if (! $key) {
            return false;
        }

        MessageOptOut::firstOrCreate(['phone' => $key], ['source' => $source, 'event_id' => $eventId]);

        return true;
    }

    public function isOptedOut(?string $phone): bool
    {
        $key = $this->key($phone);

        return $key !== null && MessageOptOut::where('phone', $key)->exists();
    }

    /**
     * @param  array<int,string>  $phones
     * @return array<string,true> opted-out keys among the given numbers
     */
    public function suppressedAmong(array $phones): array
    {
        $keys = array_values(array_filter(array_map(fn ($p) => $this->key($p), $phones)));

        if ($keys === []) {
            return [];
        }

        return array_fill_keys(MessageOptOut::whereIn('phone', $keys)->pluck('phone')->all(), true);
    }

    /** Is this phone inside a set returned by suppressedAmong()? Used by list pages to show an "Opted out" badge. */
    public function inSet(array $set, ?string $phone): bool
    {
        $key = $this->key($phone);

        return $key !== null && isset($set[$key]);
    }

    /** For the WhatsApp click-to-chat buttons: stop before opening a chat with someone who opted out. */
    public function blockedRedirect(?string $phone): ?RedirectResponse
    {
        return $this->isOptedOut($phone)
            ? back()->with('error', 'This person asked not to receive messages, so no message was prepared.')
            : null;
    }
}
