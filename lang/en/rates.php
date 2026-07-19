<?php

return [
    'not_loaded' => 'Currency and gold rates are not loaded yet. Price calculation is not reliable.',
    'currency_invalid' => 'Currency rates could not be read or are invalid. Price calculation is not reliable.',
    'gold_invalid' => 'Gold rates could not be read or are invalid. Price calculation is not reliable.',
    'stale' => 'Rates are older than :age (last update: :date). Price calculation is not reliable.',
    'age' => [
        'one_hour' => '1 hour',
        'hours' => ':count hours',
        'minutes' => ':count minutes',
        'seconds' => ':count seconds',
    ],
    'update_failed' => 'Rates could not be updated: :error',
    'currency_api_failed' => 'Altinkaynak currency API did not respond: :url',
    'gold_api_failed' => 'Altinkaynak gold API did not respond: :url',
    'json_invalid' => 'Altinkaynak JSON format is unexpected.',
];
