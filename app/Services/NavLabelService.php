<?php

namespace App\Services;

use App\Models\Event;

class NavLabelService
{
    /**
     * Base terminology, overridden per event type below. "Event Management" (renamed
     * from "Committee") applies to every event type universally.
     */
    private const BASE = [
        'home' => 'Home',
        'financial' => 'Financial Status',
        'pledges' => 'Pledges',
        'providers' => 'Service Provider',
        'committees' => 'Event Management',
        'schedule' => 'Ceremony Schedule',
        'team' => 'Team Management',
        'invitations' => 'Guest Management',
        'settings' => 'Event Setting',
    ];

    public function for(?Event $event): array
    {
        $labels = self::BASE;
        $type = $event?->event_type;

        if (in_array($type, ['Graduation', 'Baptism'], true)) {
            $labels['pledges'] = 'Contribution';
        } elseif ($type === 'Funeral') {
            $labels['pledges'] = 'Condolences';
            $labels['schedule'] = 'Schedule';
            $labels['invitations'] = 'Announcement';
        }

        return $labels;
    }

    /**
     * The ordered nav item list for the current user, respecting role and event type
     * (Team Management is hidden entirely for Funeral events; admin-only items are
     * hidden for viewers). Each item carries a `group` key so the dropdown can render
     * section dividers — null for Home, which sits alone at the top as the landing
     * item rather than belonging to a category.
     */
    public function itemsFor(?Event $event, bool $isAdmin): array
    {
        $labels = $this->for($event);
        $isFuneral = $event?->event_type === 'Funeral';

        $items = [
            ['id' => 'home', 'label' => $labels['home'], 'icon' => 'house', 'group' => null],

            // FINANCES — the summary view, then the two pages that drive it.
            ['id' => 'financial', 'label' => $labels['financial'], 'icon' => 'chart-pie', 'group' => 'Finances'],
            ['id' => 'pledges', 'label' => $labels['pledges'], 'icon' => 'hand-holding-dollar', 'group' => 'Finances'],
            ['id' => 'providers', 'label' => $labels['providers'], 'icon' => 'truck-fast', 'group' => 'Finances'],

            // PEOPLE — everyone the organizer needs to coordinate: guests, helpers, staff.
            ['id' => 'invitations', 'label' => $labels['invitations'], 'icon' => 'envelope-open-text', 'group' => 'People'],
            ['id' => 'committees', 'label' => $labels['committees'], 'icon' => 'people-roof', 'group' => 'People'],
        ];

        if ($isAdmin && ! $isFuneral) {
            $items[] = ['id' => 'team', 'label' => $labels['team'], 'icon' => 'user-group', 'group' => 'People'];
        }

        // EVENT — the event's own logistics and configuration.
        $items[] = ['id' => 'schedule', 'label' => $labels['schedule'], 'icon' => 'calendar-days', 'group' => 'Event'];

        if ($isAdmin) {
            $items[] = ['id' => 'settings', 'label' => $labels['settings'], 'icon' => 'gear', 'group' => 'Event'];
        }

        return $items;
    }
}
