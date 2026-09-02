<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
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

        $logs = ActivityLog::query()
            ->with(['actor', 'targetUser', 'event'])
            ->when($userId, fn ($q) => $q->where('target_user_id', $userId))
            ->when($search, function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%");
            })
            ->latest('created_at')
            ->paginate(30)
            ->withQueryString();

        $filteredUser = $userId ? User::find($userId) : null;

        return view('admin.logs.index', compact('logs', 'search', 'filteredUser'));
    }
}
