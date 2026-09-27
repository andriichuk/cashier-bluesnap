<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap\Webhooks;

use Andriichuk\CashierBlueSnap\Cashier;
use Andriichuk\CashierBlueSnap\Customer;
use Andriichuk\CashierBlueSnap\Subscription;
use Andriichuk\CashierBlueSnap\Transaction;
use Andriichuk\CashierBlueSnap\ValueObjects\Money;
use Andriichuk\CashierBlueSnap\WebhookEvent;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

final class WebhookProcessor
{
    /** @var list<string> */
    private const array SUBSCRIPTION_REFRESH_EVENTS = [
        'CHARGE',
        'CONTRACT_CHANGE',
        'RECURRING',
        'SUBSCRIPTION_CHARGE_FAILURE',
    ];

    /** @var list<string> */
    private const array TRANSACTION_EVENTS = [
        'AUTH_ONLY',
        'CANCELLATION_REFUND',
        'CC_CHARGE_FAILED',
        'CC_STATUS_CHANGED',
        'CHARGE',
        'CHARGE_PENDING',
        'CHARGEBACK',
        'CHARGEBACK_STATUS_CHANGED',
        'DECLINE',
        'FRAUD_DECLINE',
        'OFFLINE_ORDER',
        'PENDING_REFUND',
        'RECURRING',
        'REFUND',
        'SUBSCRIPTION_CHARGE_FAILURE',
    ];

    public function process(WebhookEvent $webhook): bool
    {
        $subscription = $this->findSubscription($webhook->subscription_bluesnap_id);
        $changed = $this->syncSubscription($webhook, $subscription);

        if (in_array($webhook->transaction_type, self::TRANSACTION_EVENTS, true)) {
            $changed = $this->syncTransaction($webhook, $subscription) || $changed;
        }

        return $changed;
    }

    private function findSubscription(?string $subscriptionId): ?Subscription
    {
        if ($subscriptionId === null || $subscriptionId === '') {
            return null;
        }

        $model = Cashier::$subscriptionModel;
        $subscription = $model::query()->where('bluesnap_id', $subscriptionId)->first();

        return $subscription instanceof Subscription ? $subscription : null;
    }

    private function syncSubscription(WebhookEvent $webhook, ?Subscription $subscription): bool
    {
        if ($subscription === null) {
            return false;
        }

        if ($webhook->transaction_type === 'CANCELLATION') {
            $subscription->forceFill([
                'status' => Subscription::STATUS_CANCELED,
                'auto_renew' => false,
                'ends_at' => now(),
            ])->save();

            return true;
        }

        if ($webhook->transaction_type === 'CANCEL_ON_RENEWAL') {
            $subscription->forceFill([
                'auto_renew' => false,
                'ends_at' => $subscription->next_charge_at,
            ])->save();

            return true;
        }

        if (in_array($webhook->transaction_type, self::SUBSCRIPTION_REFRESH_EVENTS, true)) {
            $subscription->refreshFromBlueSnap();

            return true;
        }

        return false;
    }

    private function syncTransaction(WebhookEvent $webhook, ?Subscription $subscription): bool
    {
        if ($webhook->reference_number === null || $webhook->reference_number === '') {
            return false;
        }

        $billable = $this->resolveBillable($webhook, $subscription);

        if ($billable === null) {
            return false;
        }

        $model = Cashier::$transactionModel;
        /** @var Transaction $transaction */
        $transaction = $model::query()->firstOrNew(['bluesnap_id' => $webhook->reference_number]);
        $transaction->forceFill([
            'billable_type' => $billable->getMorphClass(),
            'billable_id' => $billable->getKey(),
            'subscription_bluesnap_id' => $subscription?->bluesnap_id,
            'type' => $webhook->transaction_type,
            'status' => $this->transactionStatus($webhook),
            'amount' => $this->amount($webhook->payload),
            'currency' => $this->stringValue($webhook->payload, ['invoiceChargeCurrency', 'currency']),
            'billed_at' => $this->transactionDate($webhook->payload),
            'raw_response' => $webhook->payload,
        ])->save();

        return true;
    }

    private function resolveBillable(WebhookEvent $webhook, ?Subscription $subscription): ?Model
    {
        if ($subscription !== null) {
            return $subscription->billable()->first();
        }

        $shopperId = $this->stringValue($webhook->payload, ['vaultedShopperId', 'accountId']);

        if ($shopperId === null) {
            return null;
        }

        $model = Cashier::$customerModel;
        $customer = $model::query()->where('vaulted_shopper_id', $shopperId)->first();

        return $customer instanceof Customer ? $customer->billable()->first() : null;
    }

    /** @param array<string, mixed> $payload */
    private function amount(array $payload): ?string
    {
        $value = $this->stringValue($payload, ['invoiceChargeAmount', 'invoiceAmount', 'amount']);

        return $value === null ? null : Money::normalizeApiAmount($value);
    }

    private function transactionStatus(WebhookEvent $webhook): string
    {
        $payloadStatus = $this->stringValue($webhook->payload, ['status', 'transactionStatus', 'cbStatus']);

        if ($payloadStatus !== null) {
            return strtoupper($payloadStatus);
        }

        return match ($webhook->transaction_type) {
            'CANCELLATION_REFUND', 'REFUND' => 'REFUNDED',
            'CC_CHARGE_FAILED', 'DECLINE', 'FRAUD_DECLINE', 'SUBSCRIPTION_CHARGE_FAILURE' => 'DECLINED',
            'CC_STATUS_CHANGED' => 'UPDATED',
            'CHARGEBACK' => 'CHARGEBACK',
            'CHARGEBACK_STATUS_CHANGED' => 'CHARGEBACK_UPDATED',
            'CHARGE_PENDING', 'PENDING_REFUND' => 'PENDING',
            default => 'APPROVED',
        };
    }

    /** @param array<string, mixed> $payload */
    private function transactionDate(array $payload): ?Carbon
    {
        $value = $this->stringValue($payload, ['transactionDate']);

        if ($value === null) {
            return null;
        }

        foreach (['m/d/Y h:i A', 'm/d/Y H:i', DateTimeImmutable::ATOM] as $format) {
            $parsed = DateTimeImmutable::createFromFormat($format, $value);
            $errors = DateTimeImmutable::getLastErrors();

            if ($parsed !== false && (! is_array($errors) || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return Carbon::instance($parsed);
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $keys
     */
    private function stringValue(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $payload[$key] ?? null;

            if (is_int($value) || is_string($value)) {
                $value = trim((string) $value);

                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }
}
