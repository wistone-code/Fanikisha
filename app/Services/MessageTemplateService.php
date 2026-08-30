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
            '{pay_link}' => $pledge->payLink(),
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

    /**
     * Meeting invitation is now sent as a single broadcast (no more per-individual
     * sends), so this is a group message like forBroadcast/forAnnouncement rather
     * than personalized per pledge. Placeholders: {event} {place} {date}
     */
    public function forMeeting(Event $event): string
    {
        return strtr($event->messageOrDefault('meeting'), [
            '{event}' => $event->name,
            '{place}' => $event->place ?? '',
            '{date}' => $event->event_date->format('d.m.Y'),
        ]);
    }

    /** Formats every schedule item as a text list — this is what "Share" sends. */
    public function forSchedule(Event $event): string
    {
        $items = $event->scheduleItems;

        if ($items->isEmpty()) {
            return '';
        }

        $list = $items->map(function ($item) {
            $line = $item->date->format('d.m.Y').' — '.$item->title;

            if ($item->time) {
                $line .= ' at '.\Carbon\Carbon::parse($item->time)->format('g:i A');
            }

            return $line;
        })->implode("\n");

        return "{$event->name} — Schedule:\n{$list}";
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

    /**
     * Payment confirmation sent to a provider after recording a payment.
     * Fixed structure (not user-editable via a saved message, unlike the others
     * above), but the language still follows the event's sms_language setting.
     */
    public function forProviderPayment(Event $event, Provider $provider): string
    {
        $template = $event->sms_language === 'sw'
            ? 'Habari {name}, kimelipwa kiasi cha {paid} kwa ajili ya {service} ({event}), kiasi kilichobaki {remain}. Asante!'
            : 'Hello {name}, an amount of {paid} has been paid for {service} ({event}), remaining amount {remain}. Thank you!';

        return strtr($template, [
            '{name}' => $provider->name,
            '{service}' => $provider->service,
            '{event}' => $event->name,
            '{paid}' => number_format((float) $provider->paid),
            '{budget}' => number_format((float) $provider->budget),
            '{remain}' => number_format($provider->remaining()),
        ]);
    }

    /**
     * Payment confirmation sent to a pledger after recording a payment.
     * Fixed structure (not user-editable via a saved message, unlike the others
     * above), but the language still follows the event's sms_language setting.
     */
    public function forPledgePayment(Event $event, Pledge $pledge): string
    {
        $template = $event->sms_language === 'sw'
            ? 'Habari {name}, umepunguza kiasi cha {paid} kwa ajili ya {event}, bado {remain}. Asante'
            : "Dear {name}, thank you! We've recorded your payment of {paid} for {event}. Remaining balance: {remain}.";

        return strtr($template, [
            '{name}' => $pledge->name,
            '{event}' => $event->name,
            '{paid}' => number_format((float) $pledge->paid),
            '{remain}' => number_format($pledge->remaining()),
        ]);
    }
}