<?php

namespace App\Services;

/** Every word a guest sees on the card page, in English and Swahili. */
class CardLabels
{
    private const L = [
        'en' => [
            'invited' => "You're invited to",
            'dear' => 'Dear',
            'welcome_ecard' => 'we would be honoured to have you with us — we look forward to celebrating with you!',
            'welcome_contrib' => 'thank you for your contribution — we look forward to celebrating with you!',
            'will_attend' => 'Will you be attending?',
            'yes' => "Yes, I'll be there",
            'no' => "Can't make it",
            'confirmed' => "You're confirmed as attending!",
            'declined' => "Thanks for letting us know — you'll be missed!",
            'change' => 'Change response',
            'view_map' => 'Directions',
            'show_entrance' => 'Show this at the entrance for check-in',
            'your_code' => 'Card code',
            'table' => 'Your seat',
            'share' => 'Share',
            'save' => 'Save',
            'add_calendar' => 'Add to calendar',
            'open' => 'Tap to open',
            'play' => 'Play music',
            'pause' => 'Pause music',
            'video' => 'Watch video',
            'photos' => 'Event photos',
            'extra_guests' => 'Extra guests coming with you',
            'meal' => 'Meal choice',
            'dietary' => 'Dietary needs or allergies',
            'message' => 'A message for the hosts',
            'send_response' => 'Send my response',
            'closed' => 'RSVP is closed — please contact the hosts.',
            'optional' => 'optional',
            'at' => 'at',
            'saved_details' => 'Your details were saved.',
            'language' => 'Kiswahili',
            'double_card' => 'Double card',
            'single_card' => 'Single card',
            'landmark' => 'Landmark',
            'none' => 'None',
        ],
        'sw' => [
            'invited' => 'Umealikwa kwenye',
            'dear' => 'Mpendwa',
            'welcome_ecard' => 'tungefurahi sana kuwa nawe — tunatazamia kusherehekea pamoja nawe!',
            'welcome_contrib' => 'asante kwa mchango wako — tunatazamia kusherehekea pamoja nawe!',
            'will_attend' => 'Utahudhuria?',
            'yes' => 'Ndiyo, nitakuwepo',
            'no' => 'Siwezi kuja',
            'confirmed' => 'Umethibitisha kuhudhuria!',
            'declined' => 'Asante kwa kutujulisha — utakumbukwa!',
            'change' => 'Badilisha jibu',
            'view_map' => 'Maelekezo',
            'show_entrance' => 'Onyesha hii mlangoni kwa ajili ya kuingia',
            'your_code' => 'Namba ya kadi',
            'table' => 'Kiti chako',
            'share' => 'Shiriki',
            'save' => 'Hifadhi',
            'add_calendar' => 'Weka kwenye kalenda',
            'open' => 'Gusa kufungua',
            'play' => 'Cheza muziki',
            'pause' => 'Simamisha muziki',
            'video' => 'Tazama video',
            'photos' => 'Picha za tukio',
            'extra_guests' => 'Wageni wa ziada watakaokuja nawe',
            'meal' => 'Chaguo la chakula',
            'dietary' => 'Mahitaji ya chakula au mzio',
            'message' => 'Ujumbe kwa wenyeji',
            'send_response' => 'Tuma jibu langu',
            'closed' => 'Muda wa kujibu umeisha — tafadhali wasiliana na wenyeji.',
            'optional' => 'si lazima',
            'at' => 'saa',
            'saved_details' => 'Taarifa zako zimehifadhiwa.',
            'language' => 'English',
            'double_card' => 'Kadi ya wawili',
            'single_card' => 'Kadi ya mmoja',
            'landmark' => 'Alama ya karibu',
            'none' => 'Hakuna',
        ],
    ];

    public static function for(string $lang): array
    {
        return self::L[$lang] ?? self::L['en'];
    }

    public static function normalize(?string $lang, string $fallback = 'en'): string
    {
        return in_array($lang, ['en', 'sw'], true) ? $lang : (in_array($fallback, ['en', 'sw'], true) ? $fallback : 'en');
    }
}
