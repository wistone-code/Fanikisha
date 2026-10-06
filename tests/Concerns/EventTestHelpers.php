<?php

namespace Tests\Concerns;

use App\Models\Event;
use App\Models\EventMember;
use App\Models\Pledge;
use App\Models\User;
use Illuminate\Support\Str;

trait EventTestHelpers
{
    protected function memberOf(Event $event, string $role): User
    {
        $user = User::factory()->create();
        EventMember::create(['event_id' => $event->id, 'user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    /** @return array{0: Event, 1: User} */
    protected function ecardEvent(array $attrs = []): array
    {
        $event = Event::factory()->create(array_merge(['mode' => 'ecard', 'event_type' => 'Wedding', 'event_date' => now()->addDays(10)->toDateString()], $attrs));
        $admin = $this->memberOf($event, 'admin');

        return [$event, $admin];
    }

    protected function guestCard(Event $event, array $extra = []): Pledge
    {
        return Pledge::factory()->create(array_merge([
            'event_id' => $event->id,
            'amount' => 0,
            'paid' => 0,
            'invite_token' => Str::random(32),
            'pay_token' => Str::random(32),
            'card_type' => 'single',
        ], $extra));
    }

    /** A real (tiny) JPEG so the GD pipeline has something to decode. */
    protected function fakeJpeg(int $w = 40, int $h = 30): string
    {
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, 200, 50, 50));
        ob_start();
        imagejpeg($img);

        return (string) ob_get_clean();
    }
}
