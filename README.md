# Laravel Cashier for BlueSnap

Laravel Cashier-style customer and subscription billing for the [BlueSnap Payment API](https://developers.bluesnap.com/v8976-JSON/reference/bluesnap-payment-api-json).

> This package is an early development release. Customer and subscription lifecycle operations are implemented; webhook ingestion and reconciliation are the next milestone. Do not treat local subscription state as authoritative in production until webhook synchronization is available.

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
$subscription = $user->newSubscription('default', planId: 2283849)
    ->create([
        'paymentSource' => ['pfToken' => $request->string('pf_token')->toString()],
    ], idempotencyKey: (string) Str::uuid());
```

The builder also supports `nextChargeDate()`, `recurringAmount()`, and `withPayload()` for BlueSnap-specific fields.

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

## Current scope

Implemented now:

- Laravel package auto-discovery and BlueSnap SDK client configuration
- Polymorphic customer, subscription, and transaction tables
- Vaulted shopper create, update, and delete operations
- Subscription creation and local response synchronization
- Retrieval, plan swap, switch-charge preview, quantity changes, period-end cancellation, immediate cancellation, and renewal
- Custom model configuration and raw BlueSnap payload access
- Laravel 13 integration tests and maximum-level static analysis

Planned next:

- Signed, idempotent webhook ingestion and event dispatching
- Charge and transaction synchronization
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
