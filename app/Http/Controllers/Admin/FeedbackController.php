<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminMessage;
use App\Models\FeedbackItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;

class FeedbackController extends Controller
{
    public function index(Request $request): View
    {
        $filter = in_array($request->query('status'), ['new', 'seen', 'done'], true) ? $request->query('status') : null;
        $items = FeedbackItem::with(['user', 'event'])->when($filter, fn ($q) => $q->where('status', $filter))->latest()->paginate(30)->withQueryString();

        return view('admin.feedback.index', ['items' => $items, 'filter' => $filter, 'newCount' => FeedbackItem::unreadCount()]);
    }

    public function update(Request $request, FeedbackItem $feedback): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:new,seen,done']]);
        $feedback->update($data);

        return back()->with('status', 'Marked '.$data['status'].'.');
    }

    /** A reply to a host shows on their event as the usual banner; website visitors are answered by email or phone. */
    public function reply(Request $request, FeedbackItem $feedback): RedirectResponse
    {
        if ($feedback->source !== 'host' || ! $feedback->event_id) {
            return back()->with('error', 'Only host feedback can be answered here. Use the contact shown for website feedback.');
        }

        $data = $request->validate(['body' => ['required', 'string', 'max:300']]);
        AdminMessage::where('event_id', $feedback->event_id)->whereNull('dismissed_at')->update(['dismissed_at' => now()]);
        AdminMessage::create(['event_id' => $feedback->event_id, 'body' => trim($data['body']), 'sent_sms' => false, 'created_by' => $request->user()->id]);
        $feedback->update(['status' => 'done', 'replied_at' => now()]);

        return back()->with('status', 'Reply sent as a banner on their event.');
    }
}
