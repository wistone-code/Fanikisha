<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Pledge;
use App\Services\MessageTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\EventTestHelpers;
use Tests\TestCase;

/** {event_name} and {event_type} work in every message, however a host happens to type them. */
class MessagePlaceholdersTest extends TestCase
{
    use EventTestHelpers, RefreshDatabase;

    private function event(array $attrs = []): Event
    {
        return Event::factory()->create(array_merge(['mode' => 'contributions', 'event_type' => 'Wedding', 'event_date' => now()->addDays(20)->toDateString()], $attrs));
    }

    public function test_event_name_and_type_fill_in_however_they_are_typed(): void
    {
        $event = $this->event(['name' => 'Harusi ya Neema', 'place' => 'Mlimani City', 'sms_language' => 'en',
            'invitation_message' => 'Habari {name}, umealikwa kwenye  {event type} ya {event name }. Tarehe {date} katika ukumbi wa  {place}. Bofya: {link}']);
        $pledge = Pledge::factory()->create(['event_id' => $event->id, 'name' => 'Asha', 'invite_token' => Str::random(32), 'pay_token' => Str::random(32)]);

        $sms = app(MessageTemplateService::class)->forInvitation($event, $pledge);

        $this->assertStringContainsString('kwenye  Wedding ya Harusi ya Neema.', $sms);
        $this->assertStringContainsString('Mlimani City', $sms);
        $this->assertStringNotContainsString('{', explode('Bofya:', $sms)[0]);
    }

    public function test_event_type_is_given_in_swahili_when_messages_are_swahili(): void
    {
        $event = $this->event(['name' => 'Harusi ya Neema', 'sms_language' => 'sw', 'invitation_message' => 'Karibu {event_type}: {event_name} ({Event_Type})']);

        $this->assertSame('Karibu Harusi: Harusi ya Neema (Harusi)', $event->messageOrDefault('invitation'));
    }

    public function test_the_placeholders_work_in_other_messages_too(): void
    {
        $event = $this->event(['name' => 'Send-off ya Rehema', 'event_type' => 'Send-off', 'sms_language' => 'en', 'event_day_reminder_message' => 'Today: {event_type} - {event_name}']);

        $this->assertSame('Today: Send-off - Send-off ya Rehema', $event->messageOrDefault('event_day_reminder'));
    }

    public function test_the_message_editor_keeps_the_placeholders_instead_of_filling_them(): void
    {
        $event = $this->event(['name' => 'Neema and Juma', 'invitation_message' => 'You are invited to {event_type} {event_name}']);
        $admin = $this->memberOf($event, 'admin');

        $this->actingAs($admin)->get(route('guests.index'))->assertOk()
            ->assertSee('You are invited to {event_type} {event_name}', false)
            ->assertSee('{event_name} {event_type}', false);
    }

    public function test_rsvp_status_explains_why_one_name_counts_as_two_people(): void
    {
        $event = $this->event(['mode' => 'ecard']);
        $admin = $this->memberOf($event, 'admin');
        $this->guestCard($event, ['name' => 'Couple Winfred', 'card_type' => 'double', 'rsvp_status' => 'attending']);
        $this->guestCard($event, ['name' => 'Plus Belinda', 'card_type' => 'single', 'rsvp_status' => 'attending', 'plus_ones' => 1]);
        $this->guestCard($event, ['name' => 'Solo Sam', 'card_type' => 'single', 'rsvp_status' => 'attending']);

        $this->actingAs($admin)->get(route('guests.index', ['tab' => 'rsvp']))->assertOk()
            ->assertSee('5</strong> people expected', false)
            ->assertSee('incl. 1 partner(s) on double cards and 1 extra guest(s)', false)
            ->assertSee('guest + partner', false)
            ->assertSee('guest + 1 extra', false);
    }
}
