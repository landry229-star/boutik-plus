<?php

return [
    'patron' => [
        'name' => env('BOUTIK_PATRON_NAME', 'Patron Boutik'),
        'email' => env('BOUTIK_PATRON_EMAIL', 'patron@boutik.local'),
        'password' => env('BOUTIK_PATRON_PASSWORD'),
        'recreate_confirmation' => env('BOUTIK_PATRON_RECREATE_CONFIRM'),
    ],

    'company' => [
        'name' => env('BOUTIK_COMPANY_NAME', 'Boutik Plus'),
        'address' => env('BOUTIK_COMPANY_ADDRESS'),
        'phone' => env('BOUTIK_COMPANY_PHONE'),
        'email' => env('BOUTIK_COMPANY_EMAIL'),
        'ifu' => env('BOUTIK_COMPANY_IFU'),
        'rccm' => env('BOUTIK_COMPANY_RCCM'),
        'identifier_label' => env('BOUTIK_COMPANY_IDENTIFIER_LABEL', 'Identifiant fiscal'),
        'identifier' => env('BOUTIK_COMPANY_IDENTIFIER'),
        'logo_path' => env('BOUTIK_COMPANY_LOGO_PATH'),
        'legal_notice' => env('BOUTIK_COMPANY_LEGAL_NOTICE'),
    ],

    'currency' => env('BOUTIK_CURRENCY', 'FCFA'),

    'tax' => [
        'enabled' => (bool) env('BOUTIK_TAX_ENABLED', false),
        'rate' => (float) env('BOUTIK_TAX_RATE', 0),
        'label' => env('BOUTIK_TAX_LABEL'),
    ],
];
