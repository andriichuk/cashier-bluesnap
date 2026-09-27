<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap\Concerns;

use Andriichuk\CashierBlueSnap\Cashier;
use Andriichuk\CashierBlueSnap\Customer;
use Andriichuk\CashierBlueSnap\Exceptions\CustomerAlreadyCreated;
use Andriichuk\CashierBlueSnap\Exceptions\InvalidBillingPayload;
use Andriichuk\CashierBlueSnap\Exceptions\IncompleteBlueSnapResponse;
use Andriichuk\CashierBlueSnap\Support\BlueSnapOperation;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/** @mixin \Illuminate\Database\Eloquent\Model */
trait ManagesBlueSnapCustomers
{
    /** @return MorphOne<Customer, $this> */
    public function blueSnapCustomer(): MorphOne
    {
        return $this->morphOne(Cashier::$customerModel, 'billable');
    }

    public function hasBlueSnapCustomer(): bool
    {
        return $this->blueSnapCustomer()->exists();
    }

    /** @param array<string, mixed> $options */
    public function createAsBlueSnapCustomer(
        array $options = [],
        ?string $idempotencyKey = null,
    ): Customer {
        if (! $this->exists) {
            throw InvalidBillingPayload::because('The billable model must be persisted before creating a customer.');
        }

        return $this->getConnection()->transaction(function () use ($options, $idempotencyKey): Customer {
            $this->newQuery()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($this->hasBlueSnapCustomer()) {
                throw CustomerAlreadyCreated::forBillable($this);
            }

            $payload = array_replace($this->defaultBlueSnapCustomerPayload(), $options);
            $this->validateBlueSnapCustomerPayload($payload);

            $response = BlueSnapOperation::run(
                fn () => Cashier::client()->vaultedShoppers()->create($payload, $idempotencyKey),
            );
            $data = $response->json();
            $vaultedShopperId = $data['vaultedShopperId'] ?? $this->idFromLocation($response->location());

            if (! is_int($vaultedShopperId) && ! is_string($vaultedShopperId)) {
                throw IncompleteBlueSnapResponse::missing('vaultedShopperId');
            }

            /** @var Customer $customer */
            $customer = $this->blueSnapCustomer()->create([
                'vaulted_shopper_id' => (string) $vaultedShopperId,
                'name' => $this->blueSnapName(),
                'email' => $this->blueSnapEmail(),
                'raw_response' => $data,
            ]);

            return $customer;
        });
    }

    /** @param array<string, mixed> $options */
    public function updateBlueSnapCustomer(array $options): Customer
    {
        $customer = $this->blueSnapCustomer()->firstOrFail();
        $this->validateBlueSnapCustomerPayload($options);

        return $customer->getConnection()->transaction(function () use ($customer, $options): Customer {
            $customer->newQuery()->whereKey($customer->getKey())->lockForUpdate()->firstOrFail();
            $customer->refresh();

            $response = BlueSnapOperation::run(
                fn () => Cashier::client()->vaultedShoppers()->update($customer->vaulted_shopper_id, $options),
            );
            $data = $response->json();

            $customer->forceFill([
                'name' => $this->blueSnapName(),
                'email' => $this->blueSnapEmail(),
                'raw_response' => $data,
            ])->save();

            $customer->refresh();

            return $customer;
        });
    }

    public function deleteBlueSnapCustomer(): void
    {
        $customer = $this->blueSnapCustomer()->firstOrFail();

        $customer->getConnection()->transaction(function () use ($customer): void {
            $customer->newQuery()->whereKey($customer->getKey())->lockForUpdate()->firstOrFail();
            $customer->refresh();

            BlueSnapOperation::run(
                fn () => Cashier::client()->vaultedShoppers()->delete($customer->vaulted_shopper_id),
            );
            $customer->delete();
        });
    }

    public function blueSnapName(): string
    {
        $name = $this->getAttribute('name');

        return is_string($name) ? $name : '';
    }

    public function blueSnapEmail(): ?string
    {
        $email = $this->getAttribute('email');

        return is_string($email) && $email !== '' ? $email : null;
    }

    /** @return array<string, scalar|null> */
    protected function defaultBlueSnapCustomerPayload(): array
    {
        $name = trim($this->blueSnapName());
        [$firstName, $lastName] = array_pad(preg_split('/\s+/', $name, 2) ?: [], 2, '');

        return array_filter([
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $this->blueSnapEmail(),
            'merchantShopperId' => $this->blueSnapMerchantShopperId(),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function blueSnapMerchantShopperId(): ?string
    {
        $key = $this->getKey();

        return is_int($key) || is_string($key) ? (string) $key : null;
    }

    private function idFromLocation(?string $location): ?string
    {
        if ($location === null) {
            return null;
        }

        $path = parse_url($location, PHP_URL_PATH);

        if (! is_string($path)) {
            return null;
        }

        $id = basename(rtrim($path, '/'));

        return $id !== '' ? $id : null;
    }

    /** @param array<string, mixed> $payload */
    private function validateBlueSnapCustomerPayload(array $payload): void
    {
        if (isset($payload['pfToken']) && (! is_string($payload['pfToken']) || trim($payload['pfToken']) === '')) {
            throw InvalidBillingPayload::because('The Hosted Payment Fields token must be a non-empty string.');
        }

        if (isset($payload['email'])
            && (! is_string($payload['email']) || filter_var($payload['email'], FILTER_VALIDATE_EMAIL) === false)) {
            throw InvalidBillingPayload::because('The BlueSnap customer email address is invalid.');
        }
    }
}
