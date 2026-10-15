<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One piece of feedback to the System Admin: from a host inside the app, or from a visitor on the public website. */
class FeedbackItem extends Model
{
    public const TYPES = ['problem' => 'Problem', 'idea' => 'Idea', 'question' => 'Question', 'praise' => 'Praise'];

    protected $fillable = ['source', 'type', 'rating', 'message', 'name', 'contact', 'user_id', 'event_id', 'page', 'status', 'replied_at'];

    protected function casts(): array
    {
        return ['replied_at' => 'datetime'];
    }

    public function user() { return $this->belongsTo(User::class); }

    public function event() { return $this->belongsTo(Event::class); }

    public static function unreadCount(): int
    {
        return static::where('status', 'new')->count();
    }
}
