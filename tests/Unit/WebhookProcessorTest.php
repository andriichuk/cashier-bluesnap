<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap\Tests\Unit;

use Andriichuk\CashierBlueSnap\Events\WebhookHandled;
use Andriichuk\CashierBlueSnap\Jobs\ProcessWebhook;
use Andriichuk\CashierBlueSnap\Subscription;
use Andriichuk\CashierBlueSnap\Tests\Fixtures\User;
use Andriichuk\CashierBlueSnap\Tests\TestCase;
use Andriichuk\CashierBlueSnap\Transaction;
use Andriichuk\CashierBlueSnap\WebhookEvent;
use Illuminate\Support\Facades\Event;
use LogicException;

final class WebhookProcessorTest extends TestCase
{
    public function test_a_cancellation_updates_the_local_subscription(): void
    {
        Event::fake();
        $subscription = $this->subscription();
        $webhook = $this->webhook('CANCELLATION', [
            'transactionType' => 'CANCELLATION',
            'subscriptionId' => 'sub-1',
        ]);

        $this->app->call([new ProcessWebhook($webhook->id), 'handle']);

        $subscription->refresh();
        $webhook->refresh();
        self::assertSame(Subscription::STATUS_CANCELED, $subscription->status);
        self::assertFalse($subscription->auto_renew);
        self::assertNotNull($subscription->ends_at);
        self::assertSame(WebhookEvent::STATUS_PROCESSED, $webhook->status);
        self::assertSame(1, $webhook->attempts);
        Event::assertDispatched(WebhookHandled::class);
    }

    public function test_a_subscription_charge_reconciles_subscription_and_transaction_state(): void
    {
        $subscription = $this->subscription();
        $this->http->queueJson([
            'subscriptionId' => 'sub-1',
            'planId' => 'plan-2',
            'status' => 'ACTIVE',
            'quantity' => 2,
            'currency' => 'USD',
            'recurringChargeAmount' => 29.99,
            'autoRenew' => true,
        ]);
        $webhook = $this->webhook('CHARGE', [
            'transactionType' => 'CHARGE',
            'subscriptionId' => 'sub-1',
            'referenceNumber' => 'txn-1',
            'invoiceChargeAmount' => '29.99',
            'invoiceChargeCurrency' => 'USD',
            'transactionDate' => '09/27/2026 01:30 PM',
        ]);

        $this->app->call([new ProcessWebhook($webhook->id), 'handle']);

        $subscription->refresh();
        self::assertSame('plan-2', $subscription->plan_id);
        self::assertSame(2, $subscription->quantity);

        $transaction = Transaction::query()->where('bluesnap_id', 'txn-1')->firstOrFail();
        self::assertSame($subscription->billable_id, $transaction->billable_id);
        self::assertSame('APPROVED', $transaction->status);
        self::assertSame('29.99', $transaction->amount);
        self::assertSame('USD', $transaction->currency);
        self::assertSame('sub-1', $transaction->subscription_bluesnap_id);
    }

    public function test_a_processing_failure_is_persisted_before_the_job_is_retried(): void
    {
        Event::fake();
        $this->subscription();
        $webhook = $this->webhook('CHARGE', [
            'transactionType' => 'CHARGE',
            'subscriptionId' => 'sub-1',
            'referenceNumber' => 'txn-1',
        ]);

        try {
            $this->app->call([new ProcessWebhook($webhook->id), 'handle']);
            self::fail('The missing fake BlueSnap response should fail the job.');
        } catch (LogicException $exception) {
            self::assertSame('No fake BlueSnap response was queued.', $exception->getMessage());
        }

        $webhook->refresh();
        self::assertSame(WebhookEvent::STATUS_FAILED, $webhook->status);
        self::assertSame(1, $webhook->attempts);
        self::assertNotNull($webhook->failed_at);
        self::assertSame('No fake BlueSnap response was queued.', $webhook->error);
    }

    private function subscription(): Subscription
    {
        $user = User::query()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);

        return $user->subscriptions()->create([
            'type' => 'default',
            'bluesnap_id' => 'sub-1',
            'plan_id' => 'plan-1',
            'status' => Subscription::STATUS_ACTIVE,
            'quantity' => 1,
            'currency' => 'USD',
            'auto_renew' => true,
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function webhook(string $type, array $payload): WebhookEvent
    {
        return WebhookEvent::query()->create([
            'event_key' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
            'transaction_type' => $type,
            'reference_number' => isset($payload['referenceNumber']) ? (string) $payload['referenceNumber'] : null,
            'subscription_bluesnap_id' => isset($payload['subscriptionId']) ? (string) $payload['subscriptionId'] : null,
            'status' => WebhookEvent::STATUS_QUEUED,
            'attempts' => 0,
            'payload' => $payload,
            'received_at' => now(),
        ]);
    }
}
