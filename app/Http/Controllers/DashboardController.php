<?php

namespace App\Http\Controllers;

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
        $stats = $event->stats();

        $quickLinkTypes = ['Graduation', 'Baptism', 'Funeral'];
        $quickLinks = in_array($event->event_type, $quickLinkTypes, true)
            ? ['financial', 'pledges', 'providers', 'schedule', 'invitations', 'settings']
            : null;

        return view('event.home', compact('event', 'stats', 'quickLinks'));
    }
}
