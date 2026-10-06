<?php

namespace App\Http\Controllers;

use App\Models\EventAsset;
use App\Services\CardTemplateService;
use App\Services\EventDayReminderService;
use App\Services\ImageResizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** Card look (templates, language, music, video), the host's own design, and venue / event-day details. */
class CardDesignController extends Controller
{
    private const ELEMENTS = ['name', 'qr', 'table', 'code'];

    public function index(CardTemplateService $templates): View
    {
        $event = app('currentEvent');

        return view('event.design.index', [
            'event' => $event,
            'templates' => $templates->forType($event->event_type),
            'suited' => $templates->suitedFor($event->event_type),
            'layout' => $this->layoutFor($event),
        ]);
    }

    public function updateCard(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'card_template' => ['required', 'in:'.implode(',', array_keys(CardTemplateService::TEMPLATES))],
            'card_default_lang' => ['required', 'in:en,sw'],
            'card_text_en' => ['nullable', 'string', 'max:500'],
            'card_text_sw' => ['nullable', 'string', 'max:500'],
            'card_video_url' => ['nullable', 'url', 'max:500', 'starts_with:http://,https://'],
            'card_music_url' => ['nullable', 'url', 'max:500', 'starts_with:http://,https://'],
        ]);

        app('currentEvent')->update($data);

        return back()->with('status', 'Card style saved');
    }

    public function uploadMusic(Request $request): RedirectResponse
    {
        $request->validate(['music' => ['required', 'file', 'max:3072', 'mimetypes:audio/mpeg,audio/mp3,audio/mp4,audio/x-m4a,audio/aac,audio/ogg,audio/webm']]);

        $event = app('currentEvent');
        $file = $request->file('music');

        EventAsset::updateOrCreate(
            ['event_id' => $event->id, 'kind' => 'music'],
            ['mime' => $file->getMimeType() ?: 'audio/mpeg', 'data' => file_get_contents($file->getRealPath())],
        );
        $event->update(['card_has_music' => true]);

        return back()->with('status', 'Music added to the cards');
    }

    public function removeMusic(): RedirectResponse
    {
        $event = app('currentEvent');
        EventAsset::where('event_id', $event->id)->where('kind', 'music')->delete();
        $event->update(['card_has_music' => false]);

        return back()->with('status', 'Music removed');
    }

    public function uploadDesign(Request $request, ImageResizer $resizer): RedirectResponse
    {
        $request->validate(['design' => ['required', 'file', 'image', 'mimes:png,jpg,jpeg,webp', 'max:8192']]);

        $event = app('currentEvent');
        $fit = $resizer->fit((string) file_get_contents($request->file('design')->getRealPath()), 1080);

        if (! $fit) {
            return back()->withErrors(['design' => 'That image could not be read.']);
        }

        [$bytes, $mime, $w, $h] = $fit;

        EventAsset::updateOrCreate(['event_id' => $event->id, 'kind' => 'design'], ['mime' => $mime, 'data' => $bytes]);

        $event->update([
            'card_has_custom_design' => true,
            'custom_design_width' => $w,
            'custom_design_height' => $h,
            'custom_design_layout' => json_encode($this->layoutFor($event)),
            'use_custom_design' => true,
        ]);

        return back()->with('status', 'Design uploaded — now drag the guest name and QR code where you want them.');
    }

    public function updateLayout(Request $request): RedirectResponse
    {
        $event = app('currentEvent');
        abort_unless($event->card_has_custom_design, 404);

        $data = $request->validate(['layout' => ['required', 'string', 'max:4000']]);
        $raw = json_decode($data['layout'], true);

        if (! is_array($raw)) {
            return back()->withErrors(['layout' => 'Layout could not be saved.']);
        }

        $clean = [];

        foreach (self::ELEMENTS as $key) {
            $e = $raw[$key] ?? [];
            $clean[$key] = [
                'show' => (bool) ($e['show'] ?? false),
                'x' => max(0, min(100, round((float) ($e['x'] ?? 50), 2))),
                'y' => max(0, min(100, round((float) ($e['y'] ?? 50), 2))),
                'size' => max(2, min(60, round((float) ($e['size'] ?? 6), 2))),
                'color' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($e['color'] ?? '')) ? $e['color'] : '#000000',
            ];
        }

        $event->update([
            'custom_design_layout' => json_encode($clean),
            'use_custom_design' => $request->boolean('use_custom_design'),
        ]);

        return back()->with('status', 'Card layout saved');
    }

    public function removeDesign(): RedirectResponse
    {
        $event = app('currentEvent');
        EventAsset::where('event_id', $event->id)->where('kind', 'design')->delete();
        $event->update(['card_has_custom_design' => false, 'use_custom_design' => false, 'custom_design_layout' => null, 'custom_design_width' => null, 'custom_design_height' => null]);

        return back()->with('status', 'Custom design removed — the standard card is back');
    }

    public function designImage(): Response
    {
        $asset = EventAsset::where('event_id', app('currentEvent')->id)->where('kind', 'design')->firstOrFail();

        return response($asset->data)->header('Content-Type', $asset->mime);
    }

    public function updateVenue(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'event_time' => ['nullable', 'date_format:H:i'],
            'venue_name' => ['nullable', 'string', 'max:160'],
            'venue_address' => ['nullable', 'string', 'max:255'],
            'venue_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'venue_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'landmark_note_en' => ['nullable', 'string', 'max:200'],
            'landmark_note_sw' => ['nullable', 'string', 'max:200'],
        ]);

        // A pin needs both numbers.
        if (blank($data['venue_lat'] ?? null) || blank($data['venue_lng'] ?? null)) {
            $data['venue_lat'] = $data['venue_lng'] = null;
        }

        app('currentEvent')->update($data);

        return back()->with('status', 'Venue details saved');
    }

    public function updateDayReminder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'event_day_reminder_time' => ['required', 'date_format:H:i'],
            'event_day_reminder_message' => ['nullable', 'string', 'max:1000'],
        ]);

        app('currentEvent')->update($data + ['event_day_reminder_enabled' => $request->boolean('event_day_reminder_enabled')]);

        return back()->with('status', 'Event-day reminder saved');
    }

    public function sendDayReminderNow(EventDayReminderService $service): RedirectResponse
    {
        $result = $service->send(app('currentEvent'));

        if ($result === null) {
            return back()->with('status', 'Everyone with a phone number has already had the reminder.');
        }

        return back()->with($result['successful'] ? 'status' : 'error', $result['successful']
            ? "Reminder sent to {$result['sent']} guest(s)".($result['failed'] ? ", {$result['failed']} failed" : '').'.'
            : 'SMS send failed: '.($result['error'] ?? 'Unknown error'));
    }

    /** Stored layout, or a sensible starting layout. */
    private function layoutFor($event): array
    {
        $saved = json_decode((string) $event->custom_design_layout, true);

        if (is_array($saved) && isset($saved['name'])) {
            return $saved;
        }

        return [
            'name' => ['show' => true, 'x' => 50, 'y' => 40, 'size' => 7, 'color' => '#000000'],
            'qr' => ['show' => true, 'x' => 50, 'y' => 78, 'size' => 28, 'color' => '#000000'],
            'table' => ['show' => false, 'x' => 50, 'y' => 55, 'size' => 5, 'color' => '#000000'],
            'code' => ['show' => false, 'x' => 50, 'y' => 92, 'size' => 4, 'color' => '#000000'],
        ];
    }
}
