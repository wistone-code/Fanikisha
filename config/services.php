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

    // Sends account emails over HTTPS (Railway Hobby blocks SMTP). See AccountMailer.
    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    // WhatsApp Cloud API (Meta). The templates must exist and be Active in WhatsApp Manager.
    'whatsapp' => [
        'token' => env('WHATSAPP_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'api_version' => env('WHATSAPP_API_VERSION', 'v25.0'),
        // Webhook: the word you type into Meta's "Verify token" box, and the App secret (App settings > Basic) that signs each report.
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        'invite_template' => env('WHATSAPP_TEMPLATE_INVITE', 'event_invitation'),
        'invite_template_sw' => env('WHATSAPP_TEMPLATE_INVITE_SW', 'event_invitation_sw'),
        // Used instead when the event has its own card design or photo: same text, with an Image header.
        'invite_card_template' => env('WHATSAPP_TEMPLATE_INVITE_CARD', 'event_invitation_card'),
        'invite_card_template_sw' => env('WHATSAPP_TEMPLATE_INVITE_CARD_SW', 'event_invitation_card_sw'),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
    ],

];
