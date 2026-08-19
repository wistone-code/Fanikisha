<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            // Drives terminology, theme colors, and layout across the whole app —
            // see EventThemeService and NavLabelService.
            $table->enum('event_type', [
                'Wedding', 'Engagement', 'Send-off', 'Kitchen Party', 'Baby Shower',
                'Birthday', 'Graduation', 'Baptism', 'Confirmation', 'Communion',
                'Funeral', 'Corporate',
            ]);

            $table->string('place')->nullable();
            $table->date('event_date');
            $table->date('pledge_deadline');

            // The account that created this event. Each account may own at most one
            // event — enforced in EventObserver/CreateEventRequest, not just here —
            // and this user is automatically an "admin" member of the event (see
            // EventObserver::created()). They can never be removed from their own event.
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            // Editable message templates, one per messaging surface. Each is filled by
            // MessageTemplateService using a small set of {placeholder} tokens documented
            // in that service. Null falls back to a sensible per-surface default at
            // render time — see Event::messageOrDefault().
            $table->text('provider_message')->nullable();
            $table->text('reminder_message')->nullable();     // individual pledge/condolence reminder
            $table->text('broadcast_message')->nullable();    // "remind all" broadcast text
            $table->text('invitation_message')->nullable();   // event invitation (paid-in-full RSVP link)
            $table->text('meeting_message')->nullable();      // meeting invitation
            $table->text('announcement_message')->nullable(); // Funeral: single broadcast announcement
            $table->text('committee_message')->nullable();    // Event Management member notifications

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
