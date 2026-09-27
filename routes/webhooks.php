<?php

declare(strict_types=1);

use Andriichuk\CashierBlueSnap\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/** @var list<string> $middleware */
$middleware = config('cashier-bluesnap.webhooks.middleware', []);

Route::match(['get', 'post'], (string) config('cashier-bluesnap.webhooks.path', 'bluesnap/webhook'), WebhookController::class)
    ->middleware($middleware)
    ->name('cashier-bluesnap.webhook');
