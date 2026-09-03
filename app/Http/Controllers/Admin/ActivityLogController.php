<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    /**
     * Account- and event-level activity log. Same privacy boundary as the rest
     * of the admin area — never shows pledge, provider, or guest content.
     */
    public function index(Request $request): View
    {
        $userId = $request->get('user');
        $search = trim((string) $request->get('q'));
        $period = $request->get('period', 'all');

        // Boundaries computed in Tanzania time so "today"/"this week" match what an
        // admin viewing from Tanzania actually expects — not the DB server's own
        // clock/timezone, which is exactly the mismatch fixed for the timestamps
        // themselves (see ActivityLogger).
        $now = Carbon::now('Africa/Dar_es_Salaam');
        $from = match ($period) {
            'today' => $now->clone()->startOfDay(),
            'week' => $now->clone()->startOfWeek(),
            'month' => $now->clone()->startOfMonth(),
            default => null,
        };

        $logs = ActivityLog::query()
            ->with(['actor', 'targetUser', 'event'])
            ->when($userId, fn ($q) => $q->where('target_user_id', $userId))
            ->when($search, function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%");
            })
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from->format('Y-m-d H:i:s')))
            ->latest('created_at')
            ->paginate(30)
            ->withQueryString();

        $filteredUser = $userId ? User::find($userId) : null;

        return view('admin.logs.index', compact('logs', 'search', 'filteredUser', 'period'));
    }
}
