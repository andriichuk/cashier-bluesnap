<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap;

use Andriichuk\CashierBlueSnap\Exceptions\InvalidBillingPayload;
use Andriichuk\CashierBlueSnap\Exceptions\IncompleteBlueSnapResponse;
use Andriichuk\CashierBlueSnap\Exceptions\SubscriptionAlreadyCreated;
use Andriichuk\CashierBlueSnap\Support\BlueSnapOperation;
use Andriichuk\CashierBlueSnap\Support\SubscriptionPayloadValidator;
use Andriichuk\CashierBlueSnap\ValueObjects\Money;
use Andriichuk\CashierBlueSnap\ValueObjects\PayerInfo;
use Andriichuk\CashierBlueSnap\ValueObjects\PaymentSource;
use Illuminate\Database\Eloquent\Model;

final class SubscriptionBuilder
{
    private int $quantity = 1;

    private ?PayerInfo $payer = null;

    private ?PaymentSource $paymentSource = null;

    /** @var array<string, mixed> */
    private array $payload = [];

    public function __construct(
        private readonly Model $billable,
        private readonly string $type,
        private readonly string $planId,
        private readonly string $customerName,
        private readonly ?string $customerEmail,
    ) {
        if (trim($this->type) === '') {
            throw InvalidBillingPayload::because('The subscription type cannot be empty.');
        }

        if (trim($this->planId) === '') {
            throw InvalidBillingPayload::because('The BlueSnap plan ID cannot be empty.');
        }

        if (! $this->billable->exists) {
            throw InvalidBillingPayload::because('The billable model must be persisted before subscribing.');
        }
    }

    public function quantity(int $quantity): self
    {
        if ($quantity < 1) {
            throw InvalidBillingPayload::because('Subscription quantity must be at least 1.');
        }

        $this->quantity = $quantity;

        return $this;
    }

    public function trialDays(int $days): self
    {
        if ($days < 0) {
            throw InvalidBillingPayload::because('Trial days cannot be negative.');
        }

        $this->payload['overrideTrialPeriodDays'] = $days;

        return $this;
    }

    public function nextChargeDate(string $date): self
    {
        $this->payload['nextChargeDate'] = $date;

        return $this;
    }

    public function recurringAmount(Money|int|string $amount): self
    {
        $this->payload['overrideRecurringChargeAmount'] = $amount instanceof Money
            ? $amount->amount
            : Money::normalizeAmount($amount);

        return $this;
    }

    public function payer(PayerInfo $payer): self
    {
        $this->payer = $payer;

        return $this;
    }

    public function paymentSource(PaymentSource $paymentSource): self
    {
        $this->paymentSource = $paymentSource;

        return $this;
    }

    /** @param array<string, mixed> $payload */
    public function withPayload(array $payload): self
    {
        $this->payload = array_replace($this->payload, $payload);

        return $this;
    }

    /**
     * The array form is retained as a BlueSnap escape hatch. Prefer PaymentSource.
     *
     * @param array<string, mixed>|PaymentSource|null $payment
     */
    public function create(
        array|PaymentSource|null $payment = null,
        ?string $idempotencyKey = null,
    ): Subscription {
        $connection = $this->billable->getConnection();

        return $connection->transaction(function () use ($payment, $idempotencyKey): Subscription {
            $this->billable->newQuery()
                ->whereKey($this->billable->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $subscriptions = $this->billable->morphMany(Cashier::$subscriptionModel, 'billable');

            if ($subscriptions->where('type', $this->type)->exists()) {
                throw SubscriptionAlreadyCreated::forType($this->type);
            }

            $payload = $this->buildPayload($payment);
            $customer = $this->billable->morphOne(Cashier::$customerModel, 'billable')->first();

            if ($customer instanceof Customer) {
                $payload['vaultedShopperId'] = $customer->vaulted_shopper_id;
            } elseif (! isset($payload['payerInfo'])) {
                $payload['payerInfo'] = $this->defaultPayerInfo()->toArray();
            }

            SubscriptionPayloadValidator::validate($payload, $customer instanceof Customer);

            $response = BlueSnapOperation::run(
                fn () => Cashier::client()->subscriptions()->create($payload, $idempotencyKey),
            );
            $data = $response->json();

            BlueSnapOperation::ensurePaymentSucceeded($data);

            $subscriptionId = $data['subscriptionId'] ?? null;

            if (! is_int($subscriptionId) && ! is_string($subscriptionId)) {
                throw IncompleteBlueSnapResponse::missing('subscriptionId');
            }

            if (! $customer instanceof Customer) {
                $this->storeCustomerFromSubscription($data);
            }

            $status = is_string($data['status'] ?? null)
                ? $data['status']
                : Subscription::STATUS_ACTIVE;
            $autoRenew = is_bool($data['autoRenew'] ?? null) ? $data['autoRenew'] : true;

            /** @var Subscription $subscription */
            $subscription = $subscriptions->create([
                'type' => $this->type,
                'bluesnap_id' => (string) $subscriptionId,
                'plan_id' => $this->planId,
                'status' => $status,
                'quantity' => $this->quantity,
                'auto_renew' => $autoRenew,
            ]);

            return $subscription->syncFromBlueSnap($data);
        });
    }

    /**
     * @param array<string, mixed>|PaymentSource|null $payment
     * @return array<string, mixed>
     */
    private function buildPayload(array|PaymentSource|null $payment): array
    {
        $paymentPayload = match (true) {
            $payment instanceof PaymentSource => $payment->toSubscriptionPayload(),
            is_array($payment) => $payment,
            $this->paymentSource instanceof PaymentSource => $this->paymentSource->toSubscriptionPayload(),
            default => [],
        };

        $payload = array_replace($this->payload, $paymentPayload, [
            'planId' => $this->planId,
            'quantity' => $this->quantity,
        ]);

        if ($this->payer instanceof PayerInfo) {
            $payload['payerInfo'] = $this->payer->toArray();
        }

        return $payload;
    }

    private function defaultPayerInfo(): PayerInfo
    {
        return PayerInfo::fromFullName(
            $this->customerName,
            $this->customerEmail,
            $this->merchantShopperId(),
        );
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
