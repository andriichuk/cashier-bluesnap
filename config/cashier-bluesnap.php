<?php

declare(strict_types=1);

use Andriichuk\CashierBlueSnap\Customer;
use Andriichuk\CashierBlueSnap\Subscription;
use Andriichuk\CashierBlueSnap\Transaction;
use Andriichuk\CashierBlueSnap\WebhookEvent;

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
        'webhook_event' => WebhookEvent::class,
    ],

    'webhooks' => [
        'path' => env('BLUESNAP_WEBHOOK_PATH', 'bluesnap/webhook'),
        'secret' => env('BLUESNAP_WEBHOOK_SECRET'),
        'verify_signature' => env('BLUESNAP_WEBHOOK_VERIFY_SIGNATURE', true),
        'timestamp_tolerance' => (int) env('BLUESNAP_WEBHOOK_TIMESTAMP_TOLERANCE', 300),
        'queue_connection' => env('BLUESNAP_WEBHOOK_QUEUE_CONNECTION'),
        'queue' => env('BLUESNAP_WEBHOOK_QUEUE'),
        'middleware' => [],

        // BlueSnap sets omitted webhook flags to false. Keep this list complete.
        'events' => [
            'sendAuthOnly' => true,
            'sendCancellation' => true,
            'sendCancelOnRenewal' => true,
            'sendCharge' => true,
            'sendChargeback' => true,
            'sendChargebackStatusChanged' => true,
            'sendContractChange' => true,
            'sendCancellationRefund' => true,
            'sendFailedPayoutTransfer' => false,
            'sendSubscriptionReminder' => true,
            'sendDecline' => true,
            'sendRefund' => true,
            'sendRecurring' => true,
            'sendCcFailure' => true,
            'sendSubCcFailure' => true,
            'sendUnderVendorReview' => false,
            'sendPaymentUpdate' => true,
            'sendPaymentUpdateFailure' => true,
            'sendAccountUpdater' => true,
            'sendPayout' => false,
            'sendVendorStatusChanged' => false,
            'sendFraudDecline' => true,
            'sendChargePending' => true,
            'sendShopperDeleted' => true,
            'sendVendorReportAvailable' => false,
            'sendMerchantOnboarded' => false,
            'sendOfflineOrder' => true,
            'sendCcStatusChanged' => true,
        ],
    ],
];
