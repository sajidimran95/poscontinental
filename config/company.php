<?php

return [
    /*
    | Company details used on printed documents (Sales Order, etc.).
    | Override via .env when you have the live warehouse address on file.
    */
    'address' => env('COMPANY_ADDRESS', '3802 TRADE CENTER DR'),
    'city_line' => env('COMPANY_CITY_LINE', 'ANN ARBOR, MI 48108'),
    'tel' => env('COMPANY_TEL', 'Tel:7346773510'),
    'fax' => env('COMPANY_FAX', 'Fax:7346773567'),
];
