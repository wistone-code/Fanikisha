<?php

namespace App\Services;

use App\Models\Event;

/** The card looks an event can choose from, and which ones suit which event type. */
class CardTemplateService
{
    public const TEMPLATES = [
        'classic' => ['en' => 'Classic', 'sw' => 'Kawaida'],
        'floral' => ['en' => 'Floral', 'sw' => 'Maua'],
        'elegant' => ['en' => 'Elegant gold', 'sw' => 'Dhahabu'],
        'modern' => ['en' => 'Modern', 'sw' => 'Kisasa'],
        'festive' => ['en' => 'Festive', 'sw' => 'Sherehe'],
        'kids' => ['en' => 'Playful', 'sw' => 'Watoto'],
    ];

    private const BY_TYPE = [
        'Wedding' => ['classic', 'floral', 'elegant', 'modern'],
        'Engagement' => ['elegant', 'floral', 'modern', 'classic'],
        'Send-off' => ['floral', 'classic', 'elegant'],
        'Kitchen Party' => ['floral', 'festive', 'modern'],
        'Baby Shower' => ['kids', 'floral', 'modern'],
        'Birthday' => ['festive', 'kids', 'modern'],
        'Graduation' => ['classic', 'modern', 'elegant'],
        'Baptism' => ['classic', 'floral', 'elegant'],
        'Confirmation' => ['classic', 'floral', 'elegant'],
        'Communion' => ['classic', 'floral', 'elegant'],
        'Corporate' => ['modern', 'classic', 'elegant'],
    ];

    /** Template keys offered for this event type (suited ones first), always at least three. */
    public function forType(?string $type): array
    {
        $suited = self::BY_TYPE[$type] ?? ['classic', 'modern', 'elegant'];

        return array_values(array_unique(array_merge($suited, array_keys(self::TEMPLATES))));
    }

    public function suitedFor(?string $type): array
    {
        return self::BY_TYPE[$type] ?? ['classic', 'modern', 'elegant'];
    }

    public function keyFor(Event $event): string
    {
        return array_key_exists($event->card_template, self::TEMPLATES) ? $event->card_template : 'classic';
    }
}
