<?php

// Public business details shown on the landing page, privacy policy and terms.
// Set these in Railway variables so Meta / WhatsApp business verification sees the
// SAME legal name, address and contact details as your registration documents.
return [
    'brand' => 'Fanikisha',
    'legal_name' => env('COMPANY_LEGAL_NAME', 'Fanikisha'),
    'email' => env('COMPANY_EMAIL', 'info@fanikisha.app'),
    'phone' => env('COMPANY_PHONE'),          // e.g. +255 7XX XXX XXX
    'whatsapp' => env('COMPANY_WHATSAPP'),    // digits only, e.g. 2557XXXXXXXX (optional)
    'address' => env('COMPANY_ADDRESS', 'Dar es Salaam, Tanzania'),
    'registration' => env('COMPANY_REGISTRATION'), // BRELA / TIN number (optional)
    'pdpc_certificate' => env('COMPANY_PDPC_CERTIFICATE'), // Personal Data Protection Commission certificate no.
    'updated' => '6 October 2026',
];
