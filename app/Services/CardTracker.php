<?php

namespace App\Services;

use App\Models\Pledge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Records when a guest opens their card (delivery funnel) and keeps a light, privacy-friendly
 * trail of how many different devices opened it (to spot a card that is being passed around).
 * Only a hashed, random per-browser id is stored — never an IP address or user agent.
 */
class CardTracker
{
    /** Distinct devices in 30 days at which a card is flagged as probably forwarded. */
    public const SHARED_DEVICE_THRESHOLD = 3;

    public const RETENTION_DAYS = 30;

    /** Link-preview crawlers (WhatsApp, Facebook, Telegram…) fetch the page without a person opening it. */
    public static function isBot(?string $userAgent): bool
    {
        if (blank($userAgent)) {
            return true;
        }

        return (bool) preg_match('/bot|crawl|spider|preview|facebookexternalhit|whatsapp|telegram|slack|curl|wget|python|headless/i', $userAgent);
    }

    public function recordOpen(Pledge $pledge, Request $request): void
    {
        // The organiser (or door staff) looking at a card while signed in is not a guest opening it.
        if ($request->user() || self::isBot($request->userAgent())) {
            return;
        }

        $deviceId = $request->cookie('fk_d');

        if (! is_string($deviceId) || strlen($deviceId) < 16) {
            $deviceId = Str::random(24);
            Cookie::queue(Cookie::make('fk_d', $deviceId, 60 * 24 * 365, null, null, null, true, false, 'lax'));
        }

        $now = now();

        Pledge::where('id', $pledge->id)->update([
            'first_opened_at' => DB::raw('COALESCE(first_opened_at, '.DB::getPdo()->quote($now->format('Y-m-d H:i:s')).')'),
            'last_opened_at' => $now,
            'open_count' => DB::raw('open_count + 1'),
        ]);

        $hash = substr(hash('sha256', $deviceId.'|'.$pledge->id.'|'.config('app.key')), 0, 16);

        DB::table('card_views')->insert(['pledge_id' => $pledge->id, 'device_hash' => $hash, 'created_at' => $now]);
    }

    /** @return array<int,int> pledge_id => distinct devices in the retention window, only for cards over the threshold */
    public function sharedCards(int $eventId): array
    {
        return DB::table('card_views')
            ->join('pledges', 'pledges.id', '=', 'card_views.pledge_id')
            ->where('pledges.event_id', $eventId)
            ->where('card_views.created_at', '>=', now()->subDays(self::RETENTION_DAYS))
            ->groupBy('card_views.pledge_id')
            ->havingRaw('COUNT(DISTINCT card_views.device_hash) >= ?', [self::SHARED_DEVICE_THRESHOLD])
            ->selectRaw('card_views.pledge_id as pid, COUNT(DISTINCT card_views.device_hash) as devices')
            ->pluck('devices', 'pid')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    public function prune(): int
    {
        return DB::table('card_views')->where('created_at', '<', now()->subDays(self::RETENTION_DAYS))->delete();
    }
}
