<?php

// Details printed on invoices and payment receipts. Bank and UPI live only in the server .env
// (the repository is public).
return [
    'name' => env('BUSINESS_NAME', 'Mega Techzy'),
    'address' => env('BUSINESS_ADDRESS', "Mallikarjun Nagar, Solapur\nMaharashtra, India - 413006"),
    'state' => env('BUSINESS_STATE', 'Maharashtra (27)'),
    'email' => env('BUSINESS_EMAIL', 'megatechzy@gmail.com'),
    'phone' => env('BUSINESS_PHONE', '+91 70201 62163'),
    'gstin' => env('BUSINESS_GSTIN'),
    'gst_rate' => (float) env('BUSINESS_GST_RATE', 0), // 0 = no GST line on invoices

    'bank' => [
        'account_name' => env('BANK_ACCOUNT_NAME'),
        'account_number' => env('BANK_ACCOUNT_NUMBER'),
        'ifsc' => env('BANK_IFSC'),
        'account_type' => env('BANK_ACCOUNT_TYPE', 'Savings'),
        'bank' => env('BANK_NAME'),
        'upi' => env('BANK_UPI_ID'),
    ],

    // Numbers continue the old series: A00324, A00325 … / receipts A00039 …
    'invoice_prefix' => env('INVOICE_PREFIX', 'A'),
    'invoice_start' => (int) env('INVOICE_START', 1),
    'receipt_start' => (int) env('RECEIPT_START', 1),
    'payment_days' => (int) env('INVOICE_PAYMENT_DAYS', 7),
    'terms' => [
        'Please quote invoice number when remitting funds.',
        'Please pay within 7 days from the date of invoice, overdue interest @ 14% will be charged on delayed payments.',
    ],
];
