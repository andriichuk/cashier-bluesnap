# Laravel Cashier for BlueSnap

Laravel Cashier-style customer and subscription billing for the [BlueSnap Payment API](https://developers.bluesnap.com/v8976-JSON/reference/bluesnap-payment-api-json).

> This package is an early development release. Customer and subscription lifecycle operations plus durable webhook synchronization are implemented. Exercise normal production care while the public API is still stabilizing.

## Requirements

- PHP 8.5 or newer
- Laravel 13
- BlueSnap sandbox or production API credentials

The package uses the framework-agnostic [`andriichuk/bluesnap-php-sdk`](https://github.com/andriichuk/bluesnap-php-sdk) for API communication.

## Installation

The package is available on [Packagist](https://packagist.org/packages/andriichuk/cashier-bluesnap). While the package is in early development and has no tagged release, install the `main` development branch explicitly:

```bash
composer require andriichuk/cashier-bluesnap:dev-main
php artisan vendor:publish --tag=cashier-bluesnap-migrations
php artisan migrate
```

The framework-agnostic BlueSnap SDK dependency is also resolved automatically from Packagist; no custom Composer repository entries are required.

Add the credentials to `.env`:

```dotenv
BLUESNAP_USERNAME=
BLUESNAP_PASSWORD=
BLUESNAP_ENVIRONMENT=sandbox
BLUESNAP_API_VERSION=3.0
BLUESNAP_CURRENCY=USD
BLUESNAP_WEBHOOK_SECRET=
```

The webhook receiver is available at `/bluesnap/webhook` by default. Publish the configuration file to change its path, middleware, queue, or enabled BlueSnap event types:

```bash
php artisan vendor:publish --tag=cashier-bluesnap-config
```

## Make a model billable

```php
use Andriichuk\CashierBlueSnap\Billable;
use Illuminate\Database\Eloquent\Model;

final class User extends Model
{
    use Billable;
}
```

By default, the package reads the model's `name` and `email`. Override `blueSnapName()` or `blueSnapEmail()` when your schema differs.

## Customers

BlueSnap Hosted Payment Fields can return a `pfToken`. Pass that token to create a vaulted shopper without handling raw card data in Laravel:

```php
$customer = $user->createAsBlueSnapCustomer(
    ['pfToken' => $request->string('pf_token')->toString()],
    idempotencyKey: (string) Str::uuid(),
);

$user->updateBlueSnapCustomer(['email' => 'new@example.com']);
$user->deleteBlueSnapCustomer();
```

Any valid BlueSnap vaulted-shopper fields can be supplied in the options array.

## Subscriptions

Create a subscription using a stored shopper:

```php
$subscription = $user->newSubscription('default', planId: 2283849)
    ->quantity(2)
    ->trialDays(14)
    ->create(idempotencyKey: (string) Str::uuid());
```

Or allow BlueSnap to create the vaulted shopper as part of the subscription request:

```php
use Andriichuk\CashierBlueSnap\ValueObjects\PaymentSource;

$subscription = $user->newSubscription('default', planId: 2283849)
    ->paymentSource(PaymentSource::hostedFields(
        $request->string('pf_token')->toString(),
    ))
    ->create(idempotencyKey: (string) Str::uuid());
```

For a non-tokenized card, wallet, or alternative payment method, use `PaymentSource::fromArray()` with BlueSnap's documented `paymentSource` object. Hosted Payment Fields tokens are intentionally emitted as a top-level `pfToken`, as required by BlueSnap's Create Subscription API.

The builder also supports `nextChargeDate()`, `recurringAmount()`, and `withPayload()` for BlueSnap-specific fields. Prefer decimal strings for monetary values:

```php
use Andriichuk\CashierBlueSnap\ValueObjects\Money;
use Andriichuk\CashierBlueSnap\ValueObjects\PayerInfo;
use Andriichuk\CashierBlueSnap\ValueObjects\PaymentSource;

$subscription = $user->newSubscription('default', 2283849)
    ->payer(new PayerInfo('Ada', 'Lovelace', 'ada@example.com'))
    ->paymentSource(PaymentSource::hostedFields($pfToken))
    ->recurringAmount(Money::of('29.99', 'USD'))
    ->create(idempotencyKey: (string) Str::uuid());
```

The array accepted by `create()` remains available as an escape hatch for new BlueSnap fields, but it is validated for conflicting or malformed payment and payer data.

## Subscription lifecycle

```php
$subscription = $user->subscription('default');

$subscription->refreshFromBlueSnap();
$subscription->previewSwap(planId: 2283850, quantity: 3);
$subscription->swap(planId: 2283850, quantity: 3);
$subscription->updateQuantity(4);

// Expire after the current period (autoRenew=false).
$subscription->cancel();
$subscription->resume();

// Cancel immediately (status=CANCELED).
$subscription->cancelNow();
```

Status helpers include `active()`, `recurring()`, `onTrial()`, `onGracePeriod()`, `canceled()`, `onHold()`, `suspended()`, `finished()`, and `valid()`.

```php
$user->subscribed('default');
$user->subscribed('default', planId: 2283849);
```

## Webhooks

Configure the public HTTPS endpoint through the API:

```bash
php artisan bluesnap:webhook https://billing.example.com/bluesnap/webhook
php artisan bluesnap:webhook --show
```

Then open BlueSnap's **Settings > Webhook Settings**, enable **Security Header**, and copy the generated key into `BLUESNAP_WEBHOOK_SECRET`. BlueSnap does not expose that key through the webhook-configuration API.

Incoming requests are authenticated with BlueSnap's HMAC-SHA256 security headers before parsing. The timestamp is limited to five minutes by default, the exact raw body is deduplicated in `bluesnap_webhook_events`, and processing runs through Laravel's queue. Set a real asynchronous queue driver in production and keep a worker running. A request is acknowledged only after it is durably recorded and successfully handed to the queue.

The package synchronizes known subscriptions and transactions for charge, recurring, decline, refund, chargeback, cancellation, and contract-change events. Every accepted delivery remains queryable through `WebhookEvent`, including its status, attempts, payload, and failure details. Application listeners may subscribe to:

- `WebhookReceived`
- `WebhookHandled`
- `WebhookFailed`

Signature verification is enabled by default. `BLUESNAP_WEBHOOK_VERIFY_SIGNATURE=false` is intended only for tightly controlled local tests. Set `BLUESNAP_WEBHOOK_TIMESTAMP_TOLERANCE` to change the default 300-second replay window.

## Current scope

Implemented now:

- Laravel package auto-discovery and BlueSnap SDK client configuration
- Polymorphic customer, subscription, and transaction tables
- Vaulted shopper create, update, and delete operations
- Subscription creation and local response synchronization
- Retrieval, plan swap, switch-charge preview, quantity changes, period-end cancellation, immediate cancellation, and renewal
- Custom model configuration and raw BlueSnap payload access
- Typed money, payer, and payment-source values with request validation
- Atomic local persistence and serialized customer/subscription mutations
- Explicit declined-payment and retryable-operation exceptions
- Signed, replay-resistant, idempotent webhook ingestion with queued processing
- Subscription lifecycle and transaction synchronization from core webhook events
- Webhook configuration command and Laravel lifecycle events
- Laravel 13 integration tests and maximum-level static analysis

Planned next:

- Periodic reconciliation commands
- Hosted Payment Fields helpers and saved-card 3-D Secure orchestration
- Cashier-style receipts/invoices assembled from BlueSnap charge data

See [SDK endpoint requirements](docs/SDK_ENDPOINT_REQUIREMENTS.md) for the outbound operations already available to the Laravel layer.

## Development

```bash
composer install
composer check
```

Tests use an in-memory SQLite database and a recording PSR-18 client. They never contact BlueSnap.

## Security

Do not commit BlueSnap credentials or accept raw payment-card data in your Laravel application. Please report vulnerabilities according to [SECURITY.md](SECURITY.md).

## License

Laravel Cashier for BlueSnap is open-source software licensed under the [MIT license](LICENSE.md).
