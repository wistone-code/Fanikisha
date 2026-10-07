<?php

/*
| Sales packages. Each lists the feature groups it includes — the rest of the app only ever asks
| "does this event have feature X?" (Event::hasFeature), never "which package is it?".
|
|   money  pledges, payments, reminders, providers, financials, committees, schedule, SMS broadcasts
|   cards  e-cards, RSVP, card design, delivery tracking, seating, check-in, photo wall, after-event
*/
return [
    'packages' => [
        'full' => ['label' => 'Full package', 'description' => 'All features', 'features' => ['money', 'cards']],
        'sms' => ['label' => 'SMS package', 'description' => 'Everything except e-card features', 'features' => ['money']],
        'ecard' => ['label' => 'E-card package', 'description' => 'E-cards, RSVP and check-in only — no money handling', 'features' => ['cards']],
    ],

    'default' => 'full',
];
