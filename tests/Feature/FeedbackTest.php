<?php

namespace Tests\Feature;

use App\Models\AdminMessage;
use App\Models\FeedbackItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\EventTestHelpers;
use Tests\TestCase;

class FeedbackTest extends TestCase
{
    use EventTestHelpers, RefreshDatabase;

    public function test_website_visitor_can_send_feedback_without_signing_in(): void
    {
        $this->get('/feedback')->assertOk();
        $this->post('/feedback', ['type' => 'idea', 'rating' => 4, 'message' => 'Add dark mode', 'contact' => 'a@b.co'])->assertRedirect();
        $this->assertDatabaseHas('feedback_items', ['source' => 'website', 'message' => 'Add dark mode', 'status' => 'new']);
    }

    public function test_feedback_button_shows_on_public_pages(): void
    {
        $this->get('/')->assertOk()->assertSee('/feedback', false)->assertSee('Feedback');
        $this->get('/privacy?lang=sw')->assertOk()->assertSee('Tupe maoni');
    }

    public function test_honeypot_blocks_bots(): void
    {
        $this->post('/feedback', ['type' => 'idea', 'message' => 'spam spam', 'website' => 'x'])->assertSessionHasErrors('website');
        $this->assertSame(0, FeedbackItem::count());
    }

    public function test_host_feedback_is_attached_to_user_and_event(): void
    {
        [$event, $host] = $this->ecardEvent();
        $this->actingAs($host)->post('/send-feedback', ['type' => 'problem', 'rating' => 5, 'message' => 'Cannot upload', 'page' => 'guests'])->assertRedirect();
        $this->assertDatabaseHas('feedback_items', ['source' => 'host', 'user_id' => $host->id, 'event_id' => $event->id, 'type' => 'problem', 'rating' => null]);
    }

    public function test_admin_sees_feedback_and_reply_becomes_banner(): void
    {
        [$event, $host] = $this->ecardEvent();
        $item = FeedbackItem::create(['source' => 'host', 'type' => 'question', 'message' => 'How?', 'user_id' => $host->id, 'event_id' => $event->id]);
        $admin = User::factory()->create(['is_super_user' => true]);

        $this->actingAs($admin)->get('/admin/feedback')->assertOk()->assertSee('How?');
        $this->actingAs($admin)->post("/admin/feedback/{$item->id}/reply", ['body' => 'Open Guests, tap Add.'])->assertRedirect();
        $this->assertDatabaseHas('admin_messages', ['event_id' => $event->id, 'body' => 'Open Guests, tap Add.']);
        $this->assertSame('done', $item->fresh()->status);
    }

    public function test_hosts_cannot_open_admin_feedback(): void
    {
        [, $host] = $this->ecardEvent();
        $this->actingAs($host)->get('/admin/feedback')->assertForbidden();
    }
}
