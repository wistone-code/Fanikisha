<?php

namespace App\Http\Controllers;

use App\Models\EventPhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Organiser side of the shared photo wall: settings and moderation. */
class PhotoWallController extends Controller
{
    public function index(): View
    {
        $event = app('currentEvent');

        return view('event.photos.index', [
            'event' => $event,
            'photos' => $event->photos()->orderByDesc('id')->get(['id', 'event_id', 'uploader_name', 'size', 'hidden', 'reports', 'created_at']),
            'wallUrl' => $event->photo_wall_token ? route('wall.show', $event->photo_wall_token) : null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $event = app('currentEvent');

        $data = $request->validate([
            'photo_wall_access' => ['required', 'in:link,guests'],
            'photo_wall_open_mode' => ['required', 'in:event_day,always'],
            'photo_wall_close_days' => ['required', 'integer', 'min:0', 'max:90'],
            'photo_wall_max_per_guest' => ['required', 'integer', 'min:1', 'max:50'],
            'photo_wall_max_total' => ['required', 'integer', 'min:10', 'max:1000'],
            'photo_wall_pin' => ['nullable', 'string', 'max:12'],
        ]);

        $enable = $request->boolean('photo_wall_enabled');

        $event->fill($data + [
            'photo_wall_enabled' => $enable,
            'photo_wall_uploads_blocked' => $request->boolean('photo_wall_uploads_blocked'),
        ]);

        if ($enable && ! $event->photo_wall_token) {
            $event->photo_wall_token = Str::random(32);
        }

        $event->save();

        return back()->with('status', 'Photo wall settings saved');
    }

    /** A new link makes the old one stop working (use if the link leaked). */
    public function newLink(): RedirectResponse
    {
        app('currentEvent')->update(['photo_wall_token' => Str::random(32)]);

        return back()->with('status', 'New photo wall link created — the old one no longer works');
    }

    public function toggleHidden(EventPhoto $photo): RedirectResponse
    {
        abort_unless($photo->event_id === app('currentEvent')->id, 404);
        $photo->update(['hidden' => ! $photo->hidden, 'reports' => 0]);

        return back()->with('status', $photo->hidden ? 'Photo hidden' : 'Photo visible again');
    }

    public function destroy(EventPhoto $photo): RedirectResponse
    {
        abort_unless($photo->event_id === app('currentEvent')->id, 404);
        $photo->delete();

        return back()->with('status', 'Photo deleted');
    }

    public function thumb(EventPhoto $photo): Response
    {
        abort_unless($photo->event_id === app('currentEvent')->id, 404);

        return response($photo->thumb)->header('Content-Type', 'image/jpeg');
    }

    /** Every visible photo in one ZIP, for the family album. */
    public function download(): BinaryFileResponse|RedirectResponse
    {
        $event = app('currentEvent');
        $photos = $event->photos()->where('hidden', false)->orderBy('id')->get();

        if ($photos->isEmpty()) {
            return back()->with('error', 'No photos to download yet.');
        }

        $path = tempnam(sys_get_temp_dir(), 'wall');
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::OVERWRITE);

        foreach ($photos as $i => $p) {
            $zip->addFromString(sprintf('photo-%03d.jpg', $i + 1), $p->image);
        }

        $zip->close();

        return response()->download($path, Str::slug($event->name).'-photos.zip')->deleteFileAfterSend(true);
    }
}
