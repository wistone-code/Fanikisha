<?php

namespace App\Http\Controllers;

use App\Models\EventAsset;
use App\Models\Pledge;
use App\Services\CardLabels;
use App\Services\CardTemplateService;
use App\Services\CardTracker;
use App\Services\EventThemeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The public pages a guest reaches from their card link — no login, the secret link is the key. */
class GuestCardController extends Controller
{
    private function find(string $token): ?Pledge
    {
        $pledge = Pledge::with(['event', 'seatingTable', 'seatingArea'])->where('invite_token', $token)->first();

        // Cards are not part of every package (or the account was moved to one without them).
        return $pledge && $pledge->event->hasFeature('cards') ? $pledge : null;
    }

    /** A revoked card says so plainly; a link that never existed is a normal 404. */
    private function missing(string $token): Response
    {
        if (Pledge::where('invite_token', $token)->exists()) {
            return response()->view('guest.unavailable', [], 410);
        }

        $revoked = DB::table('card_revocations')->where('token_hash', hash('sha256', $token))->exists();

        abort_unless($revoked, 404);

        return response()->view('guest.revoked', [], 410);
    }

    public function show(Request $request, string $token, CardTracker $tracker, CardTemplateService $templates): Response|\Illuminate\Contracts\View\View
    {
        $pledge = $this->find($token);

        if (! $pledge) {
            return $this->missing($token);
        }

        $event = $pledge->event;
        $tracker->recordOpen($pledge, $request);

        $lang = CardLabels::normalize($request->query('lang'), $event->card_default_lang ?? 'en');

        return view('guest.rsvp', [
            'pledge' => $pledge,
            'event' => $event,
            'theme' => app(EventThemeService::class)->forEvent($event),
            'lang' => $lang,
            'L' => CardLabels::for($lang),
            'template' => $templates->keyFor($event),
            'mealOptions' => $this->mealOptions($event),
            'rsvpOpen' => $this->rsvpOpen($event),
            'maxPlus' => $this->maxPlusOnes($pledge),
            'showSeat' => $event->seating_published && ($pledge->seating_table_id || $pledge->seating_area_id),
            'layout' => $event->use_custom_design && $event->card_has_custom_design ? json_decode((string) $event->custom_design_layout, true) : null,
            'videoEmbed' => $this->videoEmbed($event->card_video_url),
        ]);
    }

    /** "Stop messages" on the card: the number goes on the suppression list at once, for every event. */
    public function stopMessages(Request $request, string $token, \App\Services\OptOutService $optOuts): RedirectResponse
    {
        $pledge = $this->find($token);
        abort_unless($pledge, 404);

        $optOuts->add($pledge->phone, 'card', $pledge->event_id);

        return redirect()->route('guest.rsvp', array_filter(['token' => $token, 'lang' => $request->query('lang')]))->with('stopped', true);
    }

    public function respond(Request $request, string $token): RedirectResponse
    {
        $pledge = $this->find($token);
        abort_unless($pledge, 404);

        $event = $pledge->event;
        $lang = CardLabels::normalize($request->input('lang'), $event->card_default_lang ?? 'en');

        if (! $this->rsvpOpen($event)) {
            return redirect()->route('guest.rsvp', [$token, 'lang' => $lang])->with('rsvp_error', CardLabels::for($lang)['closed']);
        }

        $rules = ['response' => ['required', 'in:attending,not_attending']];
        $meals = $this->mealOptions($event);

        if ($event->rsvp_plus_ones_enabled) {
            $rules['plus_ones'] = ['nullable', 'integer', 'min:0', 'max:'.$this->maxPlusOnes($pledge)];
        }
        if ($event->rsvp_meal_enabled && $meals) {
            $rules['meal_choice'] = ['nullable', 'string', \Illuminate\Validation\Rule::in($meals)];
        }
        if ($event->rsvp_dietary_enabled) {
            $rules['dietary_note'] = ['nullable', 'string', 'max:255'];
        }
        if ($event->rsvp_message_enabled) {
            $rules['host_message'] = ['nullable', 'string', 'max:500'];
        }

        $data = $request->validate($rules);

        $update = ['rsvp_status' => $data['response'], 'rsvp_at' => now()];

        if ($data['response'] === 'not_attending') {
            $update['plus_ones'] = 0;
        } else {
            foreach (['plus_ones', 'meal_choice', 'dietary_note'] as $field) {
                if (array_key_exists($field, $data)) {
                    $update[$field] = $field === 'plus_ones' ? (int) $data[$field] : ($data[$field] ?: null);
                }
            }
        }

        if (array_key_exists('host_message', $data)) {
            $update['host_message'] = $data['host_message'] ?: null;
        }

        $pledge->update($update);

        return redirect()->route('guest.rsvp', [$token, 'lang' => $lang]);
    }

    public function photo(string $token): Response
    {
        $pledge = $this->find($token);
        abort_unless($pledge && $pledge->event->hasCardPhoto(), 404);

        return response($pledge->event->card_photo)->header('Content-Type', $pledge->event->card_photo_mime)->header('Cache-Control', 'public, max-age=3600');
    }

    public function design(string $token): Response
    {
        $pledge = $this->find($token);
        abort_unless($pledge && $pledge->event->card_has_custom_design, 404);

        $asset = EventAsset::where('event_id', $pledge->event_id)->where('kind', 'design')->firstOrFail();

        return response($asset->data)->header('Content-Type', $asset->mime)->header('Cache-Control', 'public, max-age=3600');
    }

    /** Uploaded background music, with Range support so phones can seek and start playing quickly. */
    public function music(Request $request, string $token): Response|StreamedResponse
    {
        $pledge = $this->find($token);
        abort_unless($pledge && $pledge->event->card_has_music, 404);

        $asset = EventAsset::where('event_id', $pledge->event_id)->where('kind', 'music')->firstOrFail();
        $data = $asset->data;
        $size = strlen($data);
        $headers = ['Content-Type' => $asset->mime, 'Accept-Ranges' => 'bytes', 'Cache-Control' => 'public, max-age=3600'];

        if (preg_match('/bytes=(\d*)-(\d*)/', (string) $request->header('Range'), $m)) {
            $start = $m[1] === '' ? max(0, $size - (int) $m[2]) : (int) $m[1];
            $end = $m[2] === '' || $m[1] === '' ? $size - 1 : min((int) $m[2], $size - 1);

            if ($start > $end || $start >= $size) {
                return response('', 416, ['Content-Range' => "bytes */{$size}"]);
            }

            return response(substr($data, $start, $end - $start + 1), 206, $headers + [
                'Content-Range' => "bytes {$start}-{$end}/{$size}",
                'Content-Length' => $end - $start + 1,
            ]);
        }

        return response($data, 200, $headers + ['Content-Length' => $size]);
    }

    /** A calendar file the guest can open to add the event with a reminder. */
    public function calendar(string $token): Response
    {
        $pledge = $this->find($token);
        abort_unless($pledge, 404);

        $event = $pledge->event;
        $tz = config('app.timezone');
        $esc = fn (string $s) => str_replace(["\\", ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $s);

        if ($event->event_time) {
            $start = $event->event_date->copy()->setTimeFromTimeString($event->event_time);
            $dtStart = 'DTSTART;TZID='.$tz.':'.$start->format('Ymd\THis');
            $dtEnd = 'DTEND;TZID='.$tz.':'.$start->copy()->addHours(4)->format('Ymd\THis');
        } else {
            $dtStart = 'DTSTART;VALUE=DATE:'.$event->event_date->format('Ymd');
            $dtEnd = 'DTEND;VALUE=DATE:'.$event->event_date->copy()->addDay()->format('Ymd');
        }

        $lines = [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Fanikisha//E-card//EN', 'CALSCALE:GREGORIAN', 'BEGIN:VEVENT',
            'UID:event-'.$event->id.'-'.$pledge->id.'@fanikisha.app',
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            $dtStart, $dtEnd,
            'SUMMARY:'.$esc($event->name),
            'LOCATION:'.$esc($event->venueLine()),
            'DESCRIPTION:'.$esc($pledge->inviteLink() ?? ''),
            'BEGIN:VALARM', 'TRIGGER:-P1D', 'ACTION:DISPLAY', 'DESCRIPTION:'.$esc($event->name), 'END:VALARM',
            'END:VEVENT', 'END:VCALENDAR',
        ];

        return response(implode("\r\n", $lines)."\r\n", 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.Str::slug($event->name).'.ics"',
        ]);
    }

    // ---- helpers -----------------------------------------------------------------------

    private function rsvpOpen($event): bool
    {
        return ! $event->rsvp_cutoff_date || ! now()->startOfDay()->gt($event->rsvp_cutoff_date);
    }

    /** @return string[] */
    private function mealOptions($event): array
    {
        if (! $event->rsvp_meal_enabled) {
            return [];
        }

        return collect(preg_split('/\r\n|\r|\n/', (string) $event->rsvp_meal_options))
            ->map(fn ($m) => trim($m))->filter()->take(10)->values()->all();
    }

    private function maxPlusOnes(Pledge $pledge): int
    {
        if (! $pledge->event->rsvp_plus_ones_enabled) {
            return 0;
        }

        return (int) ($pledge->card_type === 'double' ? $pledge->event->rsvp_max_plus_double : $pledge->event->rsvp_max_plus_single);
    }

    /** Only YouTube links are embedded in the card; any other link just opens in a new tab. */
    private function videoEmbed(?string $url): ?string
    {
        if ($url && preg_match('~(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/embed/)([A-Za-z0-9_-]{11})~', $url, $m)) {
            return 'https://www.youtube-nocookie.com/embed/'.$m[1];
        }

        return null;
    }
}
