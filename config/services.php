<?php

return [

    'beem' => [
        'api_key' => env('BEEM_API_KEY'),
        'secret_key' => env('BEEM_SECRET_KEY'),
        'sender_id' => env('BEEM_SENDER_ID', 'INFO'),

        // TZS per SMS, used only for the estimated-cost figure on the admin
        // Accounts page — set this to match your actual Beem contract rate.
        // The figure it produces is always labelled as an estimate.
        'cost_per_sms' => env('BEEM_COST_PER_SMS', 20),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
    ],

];
