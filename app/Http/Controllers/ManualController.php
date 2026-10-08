<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class ManualController extends Controller
{
    /** In-app user manual; sections shown depend on the event's package and the viewer's role. */
    public function show(): View
    {
        $event = app('currentEvent');

        return view('manual', [
            'event' => $event,
            'isAdmin' => (bool) auth()->user()->isAdminOn($event),
        ]);
    }
}
