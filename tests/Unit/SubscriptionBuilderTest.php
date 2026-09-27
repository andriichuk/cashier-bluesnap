<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap\Tests\Unit;

use Andriichuk\CashierBlueSnap\Subscription;
use Andriichuk\CashierBlueSnap\Tests\Fixtures\User;
use Andriichuk\CashierBlueSnap\Tests\TestCase;
use Carbon\CarbonImmutable;

final class SubscriptionBuilderTest extends TestCase
{
    public function test_it_creates_and_synchronizes_a_subscription_for_an_existing_customer(): void
    {
        CarbonImmutable::setTestNow('2026-09-26 12:00:00');
        $user = User::query()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);
        $user->blueSnapCustomer()->create(['vaulted_shopper_id' => '19574802']);
        $this->http->queueJson([
            'subscriptionId' => 8491543,
            'planId' => 2283849,
            'status' => 'ACTIVE',
            'quantity' => 3,
            'currency' => 'USD',
            'recurringChargeAmount' => 29.97,
            'autoRenew' => true,
            'trialPeriodDays' => 7,
            'nextChargeDate' => '2026-10-03',
        ]);

        $subscription = $user->newSubscription('default', 2283849)
            ->quantity(3)
            ->trialDays(7)
            ->create(idempotencyKey: 'subscription-1');

        self::assertSame('8491543', $subscription->bluesnap_id);
        self::assertSame('2283849', $subscription->plan_id);
        self::assertSame(3, $subscription->quantity);
        self::assertSame('29.97', $subscription->recurring_amount);
        self::assertTrue($subscription->active());
        self::assertTrue($subscription->onTrial());
        self::assertTrue($user->fresh()->subscribed());
        self::assertSame([
            'overrideTrialPeriodDays' => 7,
            'planId' => '2283849',
            'quantity' => 3,
            'vaultedShopperId' => '19574802',
        ], $this->http->requestJson());
    }

    public function test_it_records_a_customer_created_as_part_of_the_subscription(): void
    {
        $user = User::query()->create(['name' => 'Grace Hopper', 'email' => 'grace@example.com']);
        $this->http->queueJson([
            'subscriptionId' => 8491544,
            'vaultedShopperId' => 19574803,
            'planId' => 2283849,
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        $user->newSubscription('default', 2283849)->create([
            'paymentSource' => ['pfToken' => 'hosted-fields-token'],
        ]);

        self::assertSame('19574803', $user->fresh()->blueSnapCustomer->vaulted_shopper_id);
        self::assertSame([
            'paymentSource' => ['pfToken' => 'hosted-fields-token'],
            'planId' => '2283849',
            'quantity' => 1,
            'payerInfo' => [
                'firstName' => 'Grace',
                'lastName' => 'Hopper',
                'email' => 'grace@example.com',
                'merchantShopperId' => (string) $user->getKey(),
            ],
        ], $this->http->requestJson());
    }
}
