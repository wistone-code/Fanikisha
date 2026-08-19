<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Pledge;
use App\Models\Provider;

class MessageTemplateService
{
    /**
     * Fills an individual pledge/condolence reminder message.
     * Placeholders: {name} {event} {pledged} {paid} {remain}
     */
    public function forReminder(Event $event, Pledge $pledge): string
    {
        return strtr($event->messageOrDefault('reminder'), [
            '{name}' => $pledge->name,
            '{event}' => $event->name,
            '{pledged}' => number_format((float) $pledge->amount),
            '{paid}' => number_format((float) $pledge->paid),
            '{remain}' => number_format($pledge->remaining()),
        ]);
    }

    /** The "remind all" broadcast text is a single group message, not personalized. */
    public function forBroadcast(Event $event): string
    {
        return strtr($event->messageOrDefault('broadcast'), [
            '{event}' => $event->name,
            '{place}' => $event->place ?? '',
            '{date}' => $event->event_date->format('d.m.Y'),
        ]);
    }

    /**
     * Placeholders: {name} {place} {link}
     */
    public function forInvitation(Event $event, Pledge $pledge): string
    {
        return strtr($event->messageOrDefault('invitation'), [
            '{name}' => $pledge->name,
            '{place}' => $event->place ?? '',
            '{link}' => $pledge->inviteLink() ?? '',
            '{event}' => $event->name,
            '{date}' => $event->event_date->format('d.m.Y'),
        ]);
    }

    /** Placeholders: {name} {event} {place} */
    public function forMeeting(Event $event, Pledge $pledge): string
    {
        return strtr($event->messageOrDefault('meeting'), [
            '{name}' => $pledge->name,
            '{event}' => $event->name,
            '{place}' => $event->place ?? '',
        ]);
    }

    /** Funeral announcement. Pass null $pledge for the group-broadcast case ("Everyone"). */
    public function forAnnouncement(Event $event, ?Pledge $pledge): string
    {
        return strtr($event->messageOrDefault('announcement'), [
            '{name}' => $pledge?->name ?? 'Everyone',
            '{event}' => $event->name,
            '{place}' => $event->place ?? '',
            '{date}' => $event->event_date->format('d.m.Y'),
        ]);
    }

    /** Placeholders: {name} {role} {committee} */
    public function forCommittee(Event $event, Pledge $pledge, string $title, string $committeeName): string
    {
        return strtr($event->messageOrDefault('committee'), [
            '{name}' => $pledge->name,
            '{role}' => $title,
            '{committee}' => $committeeName,
        ]);
    }

    /** Placeholders: {name} {service} {budget} {event} */
    public function forProvider(Event $event, Provider $provider): string
    {
        return strtr($event->messageOrDefault('provider'), [
            '{name}' => $provider->name,
            '{service}' => $provider->service,
            '{budget}' => number_format((float) $provider->budget),
            '{event}' => $event->name,
        ]);
    }
}
