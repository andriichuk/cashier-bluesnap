<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap;

use Andriichuk\CashierBlueSnap\Exceptions\IncompleteBlueSnapResponse;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class SubscriptionBuilder
{
    private int $quantity = 1;

    /** @var array<string, mixed> */
    private array $payload = [];

    public function __construct(
        private readonly Model $billable,
        private readonly string $type,
        private readonly string $planId,
        private readonly string $customerName,
        private readonly ?string $customerEmail,
    ) {
    }

    public function quantity(int $quantity): self
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Subscription quantity must be at least 1.');
        }

        $this->quantity = $quantity;

        return $this;
    }

    public function trialDays(int $days): self
    {
        if ($days < 0) {
            throw new InvalidArgumentException('Trial days cannot be negative.');
        }

        $this->payload['overrideTrialPeriodDays'] = $days;

        return $this;
    }

    public function nextChargeDate(string $date): self
    {
        $this->payload['nextChargeDate'] = $date;

        return $this;
    }

    public function recurringAmount(int|float|string $amount): self
    {
        $this->payload['overrideRecurringChargeAmount'] = $amount;

        return $this;
    }

    /** @param array<string, mixed> $payload */
    public function withPayload(array $payload): self
    {
        $this->payload = array_replace($this->payload, $payload);

        return $this;
    }

    /**
     * @param array<string, mixed> $payment
     */
    public function create(array $payment = [], ?string $idempotencyKey = null): Subscription
    {
        $payload = array_replace($this->payload, $payment, [
            'planId' => $this->planId,
            'quantity' => $this->quantity,
        ]);

        $customer = $this->billable->morphOne(Cashier::$customerModel, 'billable')->first();

        if ($customer instanceof Customer) {
            $payload['vaultedShopperId'] = $customer->vaulted_shopper_id;
        } elseif (! isset($payload['payerInfo'])) {
            $payload['payerInfo'] = $this->defaultPayerInfo();
        }

        $response = Cashier::client()->subscriptions()->create($payload, $idempotencyKey);
        $data = $response->json();
        $subscriptionId = $data['subscriptionId'] ?? null;

        if (! is_int($subscriptionId) && ! is_string($subscriptionId)) {
            throw IncompleteBlueSnapResponse::missing('subscriptionId');
        }

        if (! $customer instanceof Customer) {
            $customer = $this->storeCustomerFromSubscription($data);
        }

        $status = is_string($data['status'] ?? null)
            ? $data['status']
            : Subscription::STATUS_ACTIVE;
        $autoRenew = is_bool($data['autoRenew'] ?? null) ? $data['autoRenew'] : true;

        /** @var Subscription $subscription */
        $subscription = $this->billable->morphMany(Cashier::$subscriptionModel, 'billable')->create([
            'type' => $this->type,
            'bluesnap_id' => (string) $subscriptionId,
            'plan_id' => $this->planId,
            'status' => $status,
            'quantity' => $this->quantity,
            'auto_renew' => $autoRenew,
        ]);

        return $subscription->syncFromBlueSnap($data);
    }

    /** @return array<string, scalar|null> */
    private function defaultPayerInfo(): array
    {
        $name = trim($this->customerName);
        [$firstName, $lastName] = array_pad(preg_split('/\s+/', $name, 2) ?: [], 2, '');

        return array_filter([
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $this->customerEmail,
            'merchantShopperId' => $this->merchantShopperId(),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /** @param array<mixed> $data */
    private function storeCustomerFromSubscription(array $data): ?Customer
    {
        $vaultedShopperId = $data['vaultedShopperId'] ?? null;

        if (! is_int($vaultedShopperId) && ! is_string($vaultedShopperId)) {
            return null;
        }

        /** @var Customer */
        return $this->billable->morphOne(Cashier::$customerModel, 'billable')->create([
            'vaulted_shopper_id' => (string) $vaultedShopperId,
            'name' => $this->customerName,
            'email' => $this->customerEmail,
            'raw_response' => $data,
        ]);
    }

    private function merchantShopperId(): ?string
    {
        $key = $this->billable->getKey();

        return is_int($key) || is_string($key) ? (string) $key : null;
    }
}
