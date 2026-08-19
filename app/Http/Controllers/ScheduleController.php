<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventOwnership;
use App\Models\ScheduleItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    use AuthorizesEventOwnership;

    public function index(Request $request): View
    {
        $event = app('currentEvent');

        return view('event.schedule.index', [
            'event' => $event,
            'items' => $event->scheduleItems,
            'isAdmin' => $request->user()->isAdminOn($event),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        app('currentEvent')->scheduleItems()->create($data);

        return back()->with('status', 'Schedule item added');
    }

    public function update(Request $request, ScheduleItem $item): RedirectResponse
    {
        $this->assertScheduleItemInCurrentEvent($item);

        $item->update($this->validated($request));

        return back()->with('status', 'Schedule item updated');
    }

    public function destroy(ScheduleItem $item): RedirectResponse
    {
        $this->assertScheduleItemInCurrentEvent($item);

        $item->delete();

        return back()->with('status', 'Schedule item deleted');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'time' => ['nullable', 'date_format:H:i'],
        ]);
    }
}
