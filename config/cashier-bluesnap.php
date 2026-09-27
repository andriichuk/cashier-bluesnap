<?php

declare(strict_types=1);

use Andriichuk\CashierBlueSnap\Customer;
use Andriichuk\CashierBlueSnap\Subscription;
use Andriichuk\CashierBlueSnap\Transaction;

return [
    'username' => env('BLUESNAP_USERNAME'),
    'password' => env('BLUESNAP_PASSWORD'),
    'environment' => env('BLUESNAP_ENVIRONMENT', 'sandbox'),
    'api_version' => env('BLUESNAP_API_VERSION', '3.0'),
    'currency' => env('BLUESNAP_CURRENCY', 'USD'),

    'models' => [
        'customer' => Customer::class,
        'subscription' => Subscription::class,
        'transaction' => Transaction::class,
    ],
];
