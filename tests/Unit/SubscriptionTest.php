<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap\Tests\Unit;

use Andriichuk\CashierBlueSnap\Subscription;
use Andriichuk\CashierBlueSnap\Tests\Fixtures\User;
use Andriichuk\CashierBlueSnap\Tests\TestCase;
use Carbon\CarbonImmutable;

final class SubscriptionTest extends TestCase
{
    public function test_it_updates_plan_and_quantity(): void
    {
        $subscription = $this->subscription();
        $this->http->queueJson([
            'subscriptionId' => 8491543,
            'planId' => 999,
            'quantity' => 4,
            'status' => 'ACTIVE',
            'autoRenew' => true,
        ]);

        $subscription->swap(999, 4);

        self::assertSame('999', $subscription->plan_id);
        self::assertSame(4, $subscription->quantity);
        self::assertSame(['planId' => '999', 'quantity' => 4], $this->http->requestJson());
    }

    public function test_quantity_updates_always_include_the_plan_id(): void
    {
        $subscription = $this->subscription();
        $this->http->queueJson(['quantity' => 2, 'status' => 'ACTIVE', 'autoRenew' => true]);

        $subscription->updateQuantity(2);

        self::assertSame(['planId' => '2283849', 'quantity' => 2], $this->http->requestJson());
        self::assertSame(2, $subscription->quantity);
    }

    public function test_it_cancels_at_period_end_and_can_resume(): void
    {
        CarbonImmutable::setTestNow('2026-09-26 12:00:00');
        $subscription = $this->subscription(['next_charge_at' => '2026-10-26 00:00:00']);
        $this->http->queueJson(['status' => 'ACTIVE', 'autoRenew' => false, 'nextChargeDate' => '2026-10-26']);
        $this->http->queueJson(['status' => 'ACTIVE', 'autoRenew' => true, 'nextChargeDate' => '2026-10-26']);

        $subscription->cancel();

        self::assertTrue($subscription->onGracePeriod());
        self::assertSame(['autoRenew' => false], $this->http->requestJson(0));

        $subscription->resume();

        self::assertTrue($subscription->recurring());
        self::assertNull($subscription->ends_at);
        self::assertSame(['autoRenew' => true], $this->http->requestJson(1));
    }

    public function test_it_cancels_immediately(): void
    {
        CarbonImmutable::setTestNow('2026-09-26 12:00:00');
        $subscription = $this->subscription();
        $this->http->queueJson(['status' => 'CANCELED', 'autoRenew' => false]);

        $subscription->cancelNow();

        self::assertTrue($subscription->canceled());
        self::assertFalse($subscription->valid());
        self::assertSame(['status' => 'CANCELED'], $this->http->requestJson());
    }

    /** @param array<string, mixed> $attributes */
    private function subscription(array $attributes = []): Subscription
    {
        $user = User::query()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);

        /** @var Subscription */
        return $user->subscriptions()->create(array_replace([
            'type' => 'default',
            'bluesnap_id' => '8491543',
            'plan_id' => '2283849',
            'status' => 'ACTIVE',
            'quantity' => 1,
            'auto_renew' => true,
        ], $attributes));
    }
}
