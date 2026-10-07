<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\Pledge;
use App\Services\ImageResizer;
use App\Services\EventThemeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** The guest-facing photo wall, reached from the secret wall link (or from a guest's card). */
class PublicPhotoWallController extends Controller
{
    public const AUTO_HIDE_REPORTS = 3;

    private function wall(string $wallToken): Event
    {
        $event = Event::where('photo_wall_enabled', true)->where('photo_wall_token', $wallToken)->firstOrFail();
        abort_unless($event->hasFeature('cards'), 404);

        return $event;
    }

    private function guestFrom(Request $request, Event $event): ?Pledge
    {
        $token = $request->query('c', $request->input('c', $request->session()->get("wall_guest_{$event->id}")));

        return $token ? $event->pledges()->where('invite_token', $token)->first() : null;
    }

    /** Null when open for uploads, otherwise the reason it is not. */
    public function uploadsClosedReason(Event $event): ?string
    {
        if ($event->photo_wall_uploads_blocked) {
            return 'Uploads have been paused by the hosts.';
        }

        $today = now()->startOfDay();

        if ($event->photo_wall_open_mode === 'event_day' && $today->lt($event->event_date)) {
            return 'The photo wall opens on '.$event->event_date->format('l, F j').'.';
        }

        if ($event->photo_wall_close_days > 0 && $today->gt($event->event_date->copy()->addDays($event->photo_wall_close_days))) {
            return 'The photo wall has closed — thank you for sharing!';
        }

        if ($event->photos()->count() >= $event->photo_wall_max_total) {
            return 'The photo wall is full.';
        }

        return null;
    }

    public function show(Request $request, string $wallToken): View
    {
        $event = $this->wall($wallToken);
        $guest = $this->guestFrom($request, $event);

        if ($guest) {
            $request->session()->put("wall_guest_{$event->id}", $guest->invite_token);
        }

        $needsPin = filled($event->photo_wall_pin) && ! $request->session()->get("wall_pin_{$event->id}");
        $needsCard = $event->photo_wall_access === 'guests' && ! $guest;

        return view('guest.wall', [
            'event' => $event,
            'theme' => app(EventThemeService::class)->forEvent($event),
            'needsPin' => $needsPin,
            'needsCard' => $needsCard,
            'photos' => ($needsPin || $needsCard) ? collect() : $event->photos()->where('hidden', false)->orderByDesc('id')->get(['id']),
            'closedReason' => $this->uploadsClosedReason($event),
            'guest' => $guest,
            'perGuest' => $event->photo_wall_max_per_guest,
        ]);
    }

    public function pin(Request $request, string $wallToken): RedirectResponse
    {
        $event = $this->wall($wallToken);
        $data = $request->validate(['pin' => ['required', 'string', 'max:12']]);

        if (! hash_equals((string) $event->photo_wall_pin, $data['pin'])) {
            return back()->withErrors(['pin' => 'That PIN is not right.']);
        }

        $request->session()->put("wall_pin_{$event->id}", true);

        return redirect()->route('wall.show', $wallToken);
    }

    public function upload(Request $request, string $wallToken, ImageResizer $resizer): JsonResponse|RedirectResponse
    {
        $event = $this->wall($wallToken);
        $guest = $this->guestFrom($request, $event);

        abort_if(filled($event->photo_wall_pin) && ! $request->session()->get("wall_pin_{$event->id}"), 403);
        abort_if($event->photo_wall_access === 'guests' && ! $guest, 403);

        if ($reason = $this->uploadsClosedReason($event)) {
            return $this->reply($request, ['ok' => false, 'message' => $reason], 403);
        }

        $request->validate([
            'photos' => ['required', 'array', 'max:5'],
            'photos.*' => ['file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
            'name' => ['nullable', 'string', 'max:80'],
        ]);

        // One person = their card (if they came from it) or a random per-browser id.
        $deviceId = $request->cookie('fk_u');
        if (! is_string($deviceId) || strlen($deviceId) < 16) {
            $deviceId = Str::random(24);
            Cookie::queue(Cookie::make('fk_u', $deviceId, 60 * 24 * 365, null, null, null, true, false, 'lax'));
        }
        $key = hash('sha256', ($guest?->invite_token ?? $deviceId).'|'.$event->id);

        $mine = $event->photos()->where('uploader_key', $key)->count();
        $room = max(0, $event->photo_wall_max_per_guest - $mine);
        $wallRoom = max(0, $event->photo_wall_max_total - $event->photos()->count());
        $saved = 0;

        foreach ($request->file('photos') as $file) {
            if ($saved >= $room || $saved >= $wallRoom) {
                break;
            }

            $processed = $resizer->process((string) file_get_contents($file->getRealPath()));

            if (! $processed) {
                continue;
            }

            $event->photos()->create([
                'uploader_key' => $key,
                'uploader_name' => $guest?->name ?? ($request->input('name') ?: null),
                'thumb' => $processed['thumb'],
                'image' => $processed['image'],
                'size' => strlen($processed['image']),
            ]);
            $saved++;
        }

        $message = $saved === 0
            ? ($room === 0 ? "You have reached the limit of {$event->photo_wall_max_per_guest} photos." : 'Those photos could not be used.')
            : "{$saved} photo(s) added — thank you!";

        return $this->reply($request, ['ok' => $saved > 0, 'message' => $message, 'saved' => $saved], $saved > 0 ? 200 : 422);
    }

    public function report(Request $request, string $wallToken, EventPhoto $photo): JsonResponse
    {
        $event = $this->wall($wallToken);
        abort_unless($photo->event_id === $event->id, 404);

        $photo->increment('reports');

        if ($photo->reports >= self::AUTO_HIDE_REPORTS) {
            $photo->update(['hidden' => true]);
        }

        return response()->json(['ok' => true]);
    }

    public function thumb(Request $request, string $wallToken, EventPhoto $photo): Response
    {
        return $this->serve($request, $wallToken, $photo, 'thumb');
    }

    public function full(Request $request, string $wallToken, EventPhoto $photo): Response
    {
        return $this->serve($request, $wallToken, $photo, 'image');
    }

    private function serve(Request $request, string $wallToken, EventPhoto $photo, string $column): Response
    {
        $event = $this->wall($wallToken);
        abort_unless($photo->event_id === $event->id && ! $photo->hidden, 404);
        abort_if(filled($event->photo_wall_pin) && ! $request->session()->get("wall_pin_{$event->id}"), 403);
        abort_if($event->photo_wall_access === 'guests' && ! $this->guestFrom($request, $event), 403);

        return response($photo->{$column})->header('Content-Type', 'image/jpeg')->header('Cache-Control', 'private, max-age=86400');
    }

    private function reply(Request $request, array $payload, int $status): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json($payload, $status);
        }

        return back()->with($payload['ok'] ? 'status' : 'error', $payload['message']);
    }
}
