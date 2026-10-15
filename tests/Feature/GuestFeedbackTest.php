<?php

namespace Tests\Feature;

use App\Services\MessageTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\EventTestHelpers;
use Tests\TestCase;

class GuestFeedbackTest extends TestCase
{
    use EventTestHelpers, RefreshDatabase;

    private function setupGuest(array $event = [], array $guest = []): array
    {
        [$e, $admin] = $this->ecardEvent(array_merge(['event_date' => today()->toDateString(), 'feedback_enabled' => true], $event));

        return [$e, $admin, $this->guestCard($e, array_merge(['checked_in_at' => now()], $guest))];
    }

    public function test_a_guest_rates_the_event_with_stars_and_a_message(): void
    {
        [$e, $admin, $g] = $this->setupGuest();

        $this->get(route('guest.feedback', $g->invite_token))->assertOk()->assertSee('How was the event');
        $this->post(route('guest.feedback.store', $g->invite_token), ['rating' => 5, 'message' => 'Lovely day, thank you'])->assertRedirect();

        $g->refresh();
        $this->assertSame(5, $g->feedback_rating);
        $this->assertSame('Lovely day, thank you', $g->feedback_message);
        $this->get(route('guest.feedback', $g->invite_token))->assertSee('Thank you for your feedback');
    }

    public function test_the_message_is_optional_but_a_rating_from_one_to_five_is_required(): void
    {
        [$e, $admin, $g] = $this->setupGuest();

        $this->post(route('guest.feedback.store', $g->invite_token), ['rating' => 4])->assertSessionHasNoErrors();
        $this->assertNull($g->fresh()->feedback_message);

        $this->post(route('guest.feedback.store', $g->invite_token), ['message' => 'x'])->assertSessionHasErrors('rating');
        $this->post(route('guest.feedback.store', $g->invite_token), ['rating' => 6])->assertSessionHasErrors('rating');
        $this->assertSame(4, $g->fresh()->feedback_rating);
    }

    public function test_a_guest_can_change_their_answer_and_it_is_still_one_response(): void
    {
        [$e, $admin, $g] = $this->setupGuest();
        $this->post(route('guest.feedback.store', $g->invite_token), ['rating' => 2, 'message' => 'Late start']);
        $this->post(route('guest.feedback.store', $g->invite_token), ['rating' => 4, 'message' => 'Better after lunch']);

        $this->assertSame(4, $g->fresh()->feedback_rating);
        $this->assertSame(1, $e->pledges()->whereNotNull('feedback_rating')->count());
    }

    public function test_feedback_opens_on_the_event_day_and_closes_after_fourteen_days(): void
    {
        [$e, $admin, $g] = $this->setupGuest(['event_date' => today()->addDay()->toDateString()]);
        $this->get(route('guest.feedback', $g->invite_token))->assertSee('opens on the day of the event');
        $this->post(route('guest.feedback.store', $g->invite_token), ['rating' => 5])->assertForbidden();

        $this->travelTo(today()->addDays(1 + 14)->setTime(10, 0));
        $this->post(route('guest.feedback.store', $g->invite_token), ['rating' => 5])->assertRedirect();

        $this->travelTo(today()->addDay()->setTime(10, 0));
        $this->post(route('guest.feedback.store', $g->invite_token), ['rating' => 3])->assertForbidden();
        $this->get(route('guest.feedback', $g->invite_token))->assertSee('Feedback is now closed');
    }

    public function test_the_page_is_not_found_when_the_host_has_not_switched_feedback_on(): void
    {
        [$e, $admin, $g] = $this->setupGuest(['feedback_enabled' => false]);

        $this->get(route('guest.feedback', $g->invite_token))->assertNotFound();
        $this->get(route('guest.feedback', 'not-a-real-token'))->assertNotFound();
    }

    public function test_swahili_events_get_a_swahili_page(): void
    {
        [$e, $admin, $g] = $this->setupGuest(['sms_language' => 'sw']);

        $this->get(route('guest.feedback', $g->invite_token))->assertSee('Hafla ilikuwaje');
    }

    public function test_the_thank_you_sms_carries_the_link_for_guests_who_came_only_when_switched_on(): void
    {
        [$e, $admin, $came] = $this->setupGuest();
        $missed = $this->guestCard($e, ['checked_in_at' => null]);
        $svc = app(MessageTemplateService::class);

        $this->assertStringContainsString($came->feedbackLink(), $svc->forThankYou($e->fresh(), $came));
        $this->assertStringNotContainsString('/feedback/', $svc->forThankYou($e->fresh(), $missed));

        $e->update(['feedback_enabled' => false]);
        $this->assertStringNotContainsString('/feedback/', $svc->forThankYou($e->fresh(), $came));
    }

    public function test_a_custom_thank_you_can_place_the_link_with_a_placeholder_without_duplicating_it(): void
    {
        [$e, $admin, $g] = $this->setupGuest(['thank_you_attended_message' => 'Thanks {name}! Tell us how it went: {feedback_link}']);

        $text = app(MessageTemplateService::class)->forThankYou($e->fresh(), $g);

        $this->assertSame(1, substr_count($text, '/feedback/'));
        $this->assertStringNotContainsString('Rate the event', $text);
    }

    public function test_the_host_sees_the_average_and_comments_and_can_switch_the_option_on(): void
    {
        [$e, $admin, $g1] = $this->setupGuest();
        $g2 = $this->guestCard($e, ['checked_in_at' => now()]);
        $g1->update(['feedback_rating' => 5, 'feedback_message' => 'Perfect', 'feedback_at' => now()]);
        $g2->update(['feedback_rating' => 4, 'feedback_at' => now()]);

        $this->actingAs($admin)->get(route('after.index'))->assertOk()->assertSee('4.5')->assertSee('Perfect');
        $this->actingAs($admin)->get(route('after.recap'))->assertOk()->assertSee('Guest feedback');

        $this->actingAs($admin)->patch(route('after.update'), ['thank_you_time' => '09:00', 'feedback_enabled' => 0])->assertRedirect();
        $this->assertFalse($e->fresh()->feedback_enabled);
    }
}
