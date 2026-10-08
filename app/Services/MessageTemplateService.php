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
        return $this->withoutLeftovers(strtr($event->messageOrDefault('broadcast'), [
            '{event}' => $event->name,
            '{place}' => $event->place ?? '',
            '{date}' => $event->event_date->format('d.m.Y'),
        ]));
    }

    /** A group message has no single person or link, so an unfilled {name}/{link} is removed instead of being sent as raw text. */
    private function withoutLeftovers(string $text): string
    {
        return trim(preg_replace('/[ \t]{2,}/', ' ', preg_replace('/\{[a-z_ ]+\}/i', '', $text)));
    }

    /**
     * Placeholders: {name} {place} {link}
     */
    /**
     * The invitation sent by SMS carries the guest's entry code instead of a link, so it works on any phone.
     * A message you wrote yourself is used as written when it has no {link}; otherwise the standard
     * text is sent. The code is always included. Contributions accounts (no cards) are unaffected.
     */
    public function forInvitationSms(Event $event, Pledge $pledge): string
    {
        if (! $event->hasFeature('cards')) {
            return $this->forInvitation($event, $pledge);
        }

        $sw = $event->sms_language === 'sw';
        $custom = $event->invitation_message;
        $template = $custom && ! str_contains($custom, '{link}')
            ? $custom
            : ($sw
                ? 'Habari {name}, umealikwa kwenye {event}! Tujiunge tarehe {date}'.($event->place ? ' katika {place}' : '').'. Namba yako ya kuingia: {code}. Itaje mlangoni.'
                : "Dear {name}, you're invited to {event}! Join us on {date}".($event->place ? ' at {place}' : '').'. Your entry code: {code}. Please give it at the door.');

        $text = trim(strtr($template, [
            '{name}' => $pledge->name,
            '{place}' => $event->place ?? '',
            '{code}' => (string) $pledge->card_code,
            '{wall_link}' => $this->wallLink($event, $pledge),
            '{event}' => $event->name,
            '{date}' => $event->event_date->format('d.m.Y'),
        ]));

        if ($pledge->card_code && ! str_contains($text, (string) $pledge->card_code)) {
            $text .= ($sw ? ' Namba yako ya kuingia: ' : ' Your entry code: ').$pledge->card_code;
        }

        return $text;
    }

    public function forInvitation(Event $event, Pledge $pledge): string
    {
        $text = trim(strtr($event->messageOrDefault('invitation'), [
            '{name}' => $pledge->name,
            '{place}' => $event->place ?? '',
            '{link}' => $event->hasFeature('cards') ? ($pledge->inviteLink() ?? '') : '',
            '{code}' => $event->hasFeature('cards') ? (string) $pledge->card_code : '',
            '{wall_link}' => $event->hasFeature('cards') ? $this->wallLink($event, $pledge) : '',
            '{event}' => $event->name,
            '{date}' => $event->event_date->format('d.m.Y'),
        ]));

        // The default invitation carries the photo wall link too when the wall is on (your own wording is left as written).
        $wall = $event->hasFeature('cards') && ! $event->invitation_message ? $this->wallLink($event, $pledge) : '';

        return $wall === '' ? $text : $text.($event->sms_language === 'sw' ? ' Tuma picha zako: ' : ' Share your photos: ').$wall;
    }

    /**
     * Meeting invitation is now sent as a single broadcast (no more per-individual
     * sends), so this is a group message like forBroadcast/forAnnouncement rather
     * than personalized per pledge. Placeholders: {event} {place} {date}
     */
    public function forMeeting(Event $event): string
    {
        return $this->withoutLeftovers(strtr($event->messageOrDefault('meeting'), [
            '{event}' => $event->name,
            '{place}' => $event->place ?? '',
            '{date}' => $event->event_date->format('d.m.Y'),
        ]));
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
    public function forProviderPayment(Event $event, Provider $provider, ?float $justPaid = null): string
    {
        $template = $event->sms_language === 'sw'
            ? 'Habari {name}, kimelipwa kiasi cha {paid} kwa ajili ya {service} ({event}), kiasi kilichobaki {remain}. Asante!'
            : 'Hello {name}, an amount of {paid} has been paid for {service} ({event}), remaining amount {remain}. Thank you!';

        return strtr($template, [
            '{name}' => $provider->name,
            '{service}' => $provider->service,
            '{event}' => $event->name,
            '{paid}' => number_format($justPaid ?? (float) $provider->paid),
            '{budget}' => number_format((float) $provider->budget),
            '{remain}' => number_format($provider->remaining()),
        ]);
    }

    /**
     * Payment confirmation sent to a pledger after recording a payment.
     * Fixed structure (not user-editable via a saved message, unlike the others
     * above), but the language still follows the event's sms_language setting.
     */
    public function forPledgePayment(Event $event, Pledge $pledge, ?float $justPaid = null): string
    {
        $template = $event->sms_language === 'sw'
            ? 'Habari {name}, umepunguza kiasi cha {paid} kwa ajili ya {event}, bado {remain}. Asante'
            : "Dear {name}, thank you! We've recorded your payment of {paid} for {event}. Remaining balance: {remain}.";

        return strtr($template, [
            '{name}' => $pledge->name,
            '{event}' => $event->name,
            '{paid}' => number_format($justPaid ?? (float) $pledge->paid),
            '{remain}' => number_format($pledge->remaining()),
        ]);
    }

    /**
     * Sent instead of any reminder once a pledger has cleared the whole amount. Fixed wording (like the
     * payment confirmation above), in the event's SMS language. Never mentions a balance.
     */
    public function forPledgeThankYou(Event $event, Pledge $pledge): string
    {
        $template = $event->sms_language === 'sw'
            ? 'Habari {name}, asante sana kwa mchango wako wa {paid} kwa ajili ya {event}. Umekamilisha mchango wako wote. Tunakushukuru!'
            : 'Dear {name}, thank you for your contribution of {paid} to {event}. Your pledge is now fully paid. We truly appreciate your support!';

        return strtr($template, [
            '{name}' => $pledge->name,
            '{event}' => $event->name,
            '{paid}' => number_format((float) $pledge->paid),
        ]);
    }

    public function forUnopenedReminder(Event $event, Pledge $pledge): string
    {
        return strtr($event->messageOrDefault('unopened_reminder'), $this->common($event, $pledge));
    }

    public function forThankYou(Event $event, Pledge $pledge): string
    {
        $attended = $pledge->checked_in_at !== null;
        $text = $event->messageOrDefault($attended ? 'thank_you_attended' : 'thank_you_absent');

        if ($attended && $event->thank_you_acknowledge_paid && $pledge->paid > 0) {
            $text .= $event->sms_language === 'sw'
                ? ' Tunashukuru pia kwa mchango wako wa '.number_format((float) $pledge->paid).'.'
                : ' Thank you also for your contribution of '.number_format((float) $pledge->paid).'.';
        }

        return strtr($text, $this->common($event, $pledge));
    }

    public function forEventDayReminder(Event $event, Pledge $pledge): string
    {
        $text = strtr($event->messageOrDefault('event_day_reminder'), $this->common($event, $pledge));

        // The default reminder also points to the photo wall when it is on (your own wording is left as written).
        $wall = $this->wallLink($event, $pledge);
        if ($wall !== '' && ! $event->event_day_reminder_message) {
            $text .= ($event->sms_language === 'sw' ? ' Tuma picha zako: ' : ' Share your photos: ').$wall;
        }

        return $text;
    }

    /** Link to the shared photo wall for this guest, or '' when the wall is off or closed to uploads. */
    private function wallLink(Event $event, Pledge $pledge): string
    {
        if (! $event->photo_wall_enabled || ! $event->photo_wall_token || $event->photo_wall_uploads_blocked) {
            return '';
        }

        return route('wall.show', array_filter(['wallToken' => $event->photo_wall_token, 'c' => $pledge->invite_token]));
    }

    /** Placeholders shared by the guest-facing messages above. */
    private function common(Event $event, Pledge $pledge): array
    {
        return [
            '{name}' => $pledge->name,
            '{event}' => $event->name,
            '{date}' => $event->event_date->format('d.m.Y'),
            '{place}' => $event->place ? ', '.$event->venueLine() : '',
            '{time}' => $event->event_time ? ($event->sms_language === 'sw' ? ' saa ' : ' at ').$event->event_time : '',
            '{link}' => $pledge->inviteLink() ?? '',
            '{code}' => (string) $pledge->card_code,
            '{wall_link}' => $this->wallLink($event, $pledge),
            '{hosts}' => $event->host_names ?: $event->name,
        ];
    }
}
