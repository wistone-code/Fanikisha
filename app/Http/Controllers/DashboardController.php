<?php

namespace App\Http\Controllers;

use App\Services\NavLabelService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** Routes the person to the right landing screen based on their account type. */
    public function index(Request $request): \Illuminate\Http\RedirectResponse|View
    {
        if ($request->user()->is_super_user) {
            return redirect()->route('admin.users.index');
        }

        $event = app('currentEvent');
        $isAdmin = $request->user()->isAdminOn($event);

        // The landing page IS the nav now (see layouts/app — the "Fanikisha" dropdown
        // is gone for regular accounts), so every account/event-type combination gets
        // the same full, correctly-filtered card list the dropdown used to show.
        $items = app(NavLabelService::class)->itemsFor($event, $isAdmin);

        return view('event.home', compact('event', 'items'));
    }
}
