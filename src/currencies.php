<?php
declare(strict_types=1);

/**
 * Currencies the Frankfurter API (ECB reference rates) can convert between.
 * BGN is left out: Bulgaria adopted the euro in 2026, so the ECB no longer publishes it.
 */
const CURRENCIES = [
    'PHP' => 'Philippine Peso',
    'USD' => 'US Dollar',
    'EUR' => 'Euro',
    'JPY' => 'Japanese Yen',
    'GBP' => 'British Pound',
    'AUD' => 'Australian Dollar',
    'CAD' => 'Canadian Dollar',
    'CHF' => 'Swiss Franc',
    'CNY' => 'Chinese Renminbi Yuan',
    'HKD' => 'Hong Kong Dollar',
    'SGD' => 'Singapore Dollar',
    'KRW' => 'South Korean Won',
    'THB' => 'Thai Baht',
    'MYR' => 'Malaysian Ringgit',
    'IDR' => 'Indonesian Rupiah',
    'INR' => 'Indian Rupee',
    'NZD' => 'New Zealand Dollar',
    'BRL' => 'Brazilian Real',
    'MXN' => 'Mexican Peso',
    'ZAR' => 'South African Rand',
    'TRY' => 'Turkish Lira',
    'ILS' => 'Israeli New Sheqel',
    'SEK' => 'Swedish Krona',
    'NOK' => 'Norwegian Krone',
    'DKK' => 'Danish Krone',
    'ISK' => 'Icelandic Króna',
    'PLN' => 'Polish Złoty',
    'CZK' => 'Czech Koruna',
    'HUF' => 'Hungarian Forint',
    'RON' => 'Romanian Leu',
];
