<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventOwnership;
use App\Models\Pledge;
use App\Models\SeatingArea;
use App\Models\SeatingTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Seating plan, phase 1: areas/zones and tables with capacities, plus who sits where. */
class SeatingController extends Controller
{
    use AuthorizesEventOwnership;

    public function index(): View
    {
        $event = app('currentEvent');
        $guests = $event->pledges()->whereNotNull('invite_token')->orderBy('name')->get();
        $tables = $event->seatingTables()->with('guests')->get();
        $areas = $event->seatingAreas()->with('guests')->get();

        $warnings = [];

        foreach ($tables as $t) {
            if ($t->seatsTaken() > $t->capacity) {
                $warnings[] = "{$t->name} is over capacity ({$t->seatsTaken()} of {$t->capacity} seats).";
            }
        }

        $unseated = $guests->filter(fn (Pledge $g) => $g->rsvp_status !== 'not_attending' && ! $g->seating_table_id && ! $g->seating_area_id);

        if ($event->seating_mode !== 'none' && $unseated->count() > 0 && $guests->count() > 0) {
            $warnings[] = "{$unseated->count()} guest(s) have no seat yet.";
        }

        // Guests sharing a group but sitting apart.
        $apart = $guests->filter(fn ($g) => $g->group_name && ($g->seating_table_id || $g->seating_area_id))
            ->groupBy('group_name')
            ->filter(fn ($g) => $g->map(fn ($x) => $x->seating_table_id ?: 'a'.$x->seating_area_id)->unique()->count() > 1);
        foreach ($apart->keys() as $group) {
            $warnings[] = "Group “{$group}” is split across more than one place.";
        }

        return view('event.seating.index', compact('event', 'guests', 'tables', 'areas', 'warnings', 'unseated'));
    }

    public function updateMode(Request $request): RedirectResponse
    {
        $data = $request->validate(['seating_mode' => ['required', 'in:none,zone,table,row']]);
        app('currentEvent')->update(['seating_mode' => $data['seating_mode']]);

        return back()->with('status', 'Seating style saved');
    }

    public function publish(Request $request): RedirectResponse
    {
        $publish = $request->boolean('seating_published');
        app('currentEvent')->update(['seating_published' => $publish]);

        return back()->with('status', $publish ? 'Seats are now shown on guest cards and at the door' : 'Seats hidden from guests');
    }

    public function storeArea(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80']]);
        $event = app('currentEvent');
        $event->seatingAreas()->create(['name' => $data['name'], 'sort_order' => $event->seatingAreas()->count()]);

        return back()->with('status', 'Area added');
    }

    public function destroyArea(SeatingArea $area): RedirectResponse
    {
        abort_unless($area->event_id === app('currentEvent')->id, 404);
        $area->delete(); // guests/tables fall back to "no area" (nullOnDelete)

        return back()->with('status', 'Area removed');
    }

    public function storeTable(Request $request): RedirectResponse
    {
        $event = app('currentEvent');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'seating_area_id' => ['nullable', 'integer'],
            'count' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);

        $areaId = $this->ownedAreaId($data['seating_area_id'] ?? null);
        $count = (int) ($data['count'] ?? 1);
        $start = $event->seatingTables()->count();

        // "Add 10 tables" creates "Table 1…10" style names from the base name.
        for ($i = 0; $i < $count; $i++) {
            $event->seatingTables()->create([
                'name' => $count > 1 ? trim($data['name']).' '.($start + $i + 1) : $data['name'],
                'capacity' => $data['capacity'],
                'seating_area_id' => $areaId,
                'sort_order' => $start + $i,
            ]);
        }

        return back()->with('status', $count > 1 ? "{$count} added" : 'Added');
    }

    public function updateTable(Request $request, SeatingTable $table): RedirectResponse
    {
        abort_unless($table->event_id === app('currentEvent')->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
        ]);
        $table->update($data);

        return back()->with('status', 'Saved');
    }

    public function destroyTable(SeatingTable $table): RedirectResponse
    {
        abort_unless($table->event_id === app('currentEvent')->id, 404);
        $table->delete();

        return back()->with('status', 'Removed');
    }

    /** Put one guest at a table / in an area (or clear it), and set their group. */
    public function assign(Request $request, Pledge $pledge): RedirectResponse
    {
        $this->assertPledgeInCurrentEvent($pledge);
        $event = app('currentEvent');

        $data = $request->validate([
            'seating_table_id' => ['nullable', 'integer'],
            'seating_area_id' => ['nullable', 'integer'],
            'seat_number' => ['nullable', 'integer', 'min:1', 'max:500'],
            'group_name' => ['nullable', 'string', 'max:80'],
        ]);

        $tableId = $data['seating_table_id'] ?? null;
        $table = $tableId ? $event->seatingTables()->find($tableId) : null;
        abort_if($tableId && ! $table, 404);

        $pledge->update([
            'seating_table_id' => $table?->id,
            // A table already belongs to an area, so the guest inherits it.
            'seating_area_id' => $table ? $table->seating_area_id : $this->ownedAreaId($data['seating_area_id'] ?? null),
            'seat_number' => $table ? ($data['seat_number'] ?? null) : null,
            'group_name' => filled($data['group_name'] ?? null) ? trim($data['group_name']) : null,
        ]);

        return back()->with('status', "{$pledge->name} updated");
    }

    /** Seats everyone without a place, group by group, into tables with room left. Never moves anyone already seated. */
    public function autoFill(): RedirectResponse
    {
        $event = app('currentEvent');
        $tables = $event->seatingTables()->with('guests')->get();

        if ($tables->isEmpty()) {
            return back()->with('error', 'Add tables first.');
        }

        $free = $tables->mapWithKeys(fn ($t) => [$t->id => $t->capacity - $t->seatsTaken()])->all();

        $todo = $event->pledges()->whereNotNull('invite_token')->whereNull('seating_table_id')
            ->where(fn ($q) => $q->whereNull('rsvp_status')->orWhere('rsvp_status', '!=', 'not_attending'))
            ->orderBy('group_name')->orderBy('name')->get();

        $placed = 0;

        // Whole groups first, so family members land at the same table when one has room.
        foreach ($todo->groupBy(fn ($g) => $g->group_name ?: '__'.$g->id) as $members) {
            $need = $members->sum(fn ($g) => $g->headcount() ?: 1);
            $tableId = collect($free)->filter(fn ($room) => $room >= $need)->sortKeys()->keys()->first()
                ?? collect($free)->sortDesc()->keys()->first();

            if ($tableId === null || ($free[$tableId] ?? 0) <= 0) {
                break;
            }

            foreach ($members as $g) {
                $g->update(['seating_table_id' => $tableId, 'seating_area_id' => $tables->firstWhere('id', $tableId)->seating_area_id]);
                $free[$tableId] -= ($g->headcount() ?: 1);
                $placed++;
            }
        }

        return back()->with('status', $placed > 0 ? "{$placed} guest(s) seated" : 'No room left at any table.');
    }

    private function ownedAreaId($id): ?int
    {
        return $id && app('currentEvent')->seatingAreas()->whereKey($id)->exists() ? (int) $id : null;
    }
}
