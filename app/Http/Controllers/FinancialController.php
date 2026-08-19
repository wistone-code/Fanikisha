<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class FinancialController extends Controller
{
    public function index(Request $request): View
    {
        $event = app('currentEvent');

        if ($event->isFuneral()) {
            return view('event.financial.funeral', ['event' => $event, 'stats' => $event->stats()]);
        }

        $tab = $request->get('tab', 'dashboard');

        if ($tab === 'individual') {
            $pledges = $event->pledges;
            $counts = ['Completed' => 0, 'Pending' => 0, 'Overdue' => 0];
            foreach ($pledges as $p) {
                $counts[$p->status()]++;
            }

            $stats = $event->stats();
            $pct = $stats['total_pledged'] > 0
                ? min(100, round($stats['collected'] / $stats['total_pledged'] * 100))
                : 0;

            return view('event.financial.pledge-status', compact('event', 'counts', 'pct', 'stats'));
        }

        return view('event.financial.dashboard', ['event' => $event, 'stats' => $event->stats()]);
    }
}
