<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap\Tests\Unit;

use Andriichuk\CashierBlueSnap\Subscription;
use Andriichuk\CashierBlueSnap\Exceptions\InvalidBillingPayload;
use Andriichuk\CashierBlueSnap\Exceptions\IncompleteBlueSnapResponse;
use Andriichuk\CashierBlueSnap\Exceptions\PaymentDeclined;
use Andriichuk\CashierBlueSnap\Exceptions\RetryableBlueSnapOperation;
use Andriichuk\CashierBlueSnap\Exceptions\SubscriptionAlreadyCreated;
use Andriichuk\CashierBlueSnap\Tests\Fixtures\User;
use Andriichuk\CashierBlueSnap\Tests\TestCase;
use Andriichuk\CashierBlueSnap\ValueObjects\Money;
use Andriichuk\CashierBlueSnap\ValueObjects\PayerInfo;
use Andriichuk\CashierBlueSnap\ValueObjects\PaymentSource;
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

        $user->newSubscription('default', 2283849)
            ->paymentSource(PaymentSource::hostedFields('hosted-fields-token'))
            ->create();

        self::assertSame('19574803', $user->fresh()->blueSnapCustomer->vaulted_shopper_id);
        self::assertSame([
            'pfToken' => 'hosted-fields-token',
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

    public function test_it_supports_explicit_typed_payer_and_money_values(): void
    {
        $user = User::query()->create(['name' => 'Company', 'email' => 'billing@example.com']);
        $this->http->queueJson([
            'subscriptionId' => 8491545,
            'vaultedShopperId' => 19574804,
            'planId' => 2283849,
            'status' => 'ACTIVE',
            'currency' => 'EUR',
            'recurringChargeAmount' => 19.9,
        ]);

        $subscription = $user->newSubscription('default', 2283849)
            ->payer(new PayerInfo('Billing', 'Team', 'billing@example.com'))
            ->paymentSource(PaymentSource::hostedFields('hosted-fields-token'))
            ->recurringAmount(Money::of('19.90', 'EUR'))
            ->create();

        self::assertSame('19.9', $subscription->recurring_amount);
        self::assertSame('19.9', $subscription->recurringMoney()?->amount);
        self::assertSame('EUR', $subscription->recurringMoney()?->currency);
        self::assertSame('19.90', $this->http->requestJson()['overrideRecurringChargeAmount']);
        self::assertSame('Billing', $this->http->requestJson()['payerInfo']['firstName']);
    }

    public function test_it_rejects_the_legacy_nested_hosted_fields_token_shape(): void
    {
        $user = User::query()->create(['name' => 'Grace Hopper', 'email' => 'grace@example.com']);

        $this->expectException(InvalidBillingPayload::class);
        $this->expectExceptionMessage('top-level');

        $user->newSubscription('default', 2283849)->create([
            'paymentSource' => ['pfToken' => 'incorrectly-nested-token'],
        ]);
    }

    public function test_it_rejects_an_invalid_next_charge_date_before_calling_bluesnap(): void
    {
        $user = User::query()->create(['name' => 'Grace Hopper', 'email' => 'grace@example.com']);
        $user->blueSnapCustomer()->create(['vaulted_shopper_id' => '19574803']);

        try {
            $user->newSubscription('default', 2283849)->nextChargeDate('2026-02-31')->create();
            self::fail('Expected invalid date validation to fail.');
        } catch (InvalidBillingPayload $exception) {
            self::assertStringContainsString('valid YYYY-MM-DD', $exception->getMessage());
            self::assertSame([], $this->http->requests);
        }
    }

    public function test_it_rejects_duplicate_subscription_types_before_calling_bluesnap(): void
    {
        $user = User::query()->create(['name' => 'Grace Hopper', 'email' => 'grace@example.com']);
        $user->subscriptions()->create([
            'type' => 'default',
            'bluesnap_id' => '8491544',
            'plan_id' => '2283849',
            'status' => 'ACTIVE',
        ]);

        $this->expectException(SubscriptionAlreadyCreated::class);

        $user->newSubscription('default', 2283849)->create();
    }

    public function test_it_does_not_persist_a_declined_subscription(): void
    {
        $user = User::query()->create(['name' => 'Grace Hopper', 'email' => 'grace@example.com']);
        $this->http->queueJson([
            'subscriptionId' => 8491544,
            'charge' => [
                'processingInfo' => [
                    'processingStatus' => 'FAIL',
                    'processingErrorDescription' => 'Card was declined.',
                ],
            ],
        ]);

        try {
            $user->newSubscription('default', 2283849)
                ->paymentSource(PaymentSource::hostedFields('hosted-fields-token'))
                ->create();
            self::fail('Expected the declined payment to fail.');
        } catch (PaymentDeclined $exception) {
            self::assertSame('Card was declined.', $exception->getMessage());
            self::assertFalse($user->subscriptions()->exists());
            self::assertFalse($user->blueSnapCustomer()->exists());
        }
    }

    public function test_it_marks_rate_limits_as_retryable_and_rolls_back_local_state(): void
    {
        $user = User::query()->create(['name' => 'Grace Hopper', 'email' => 'grace@example.com']);
        $this->http->queueJson([], 429);

        try {
            $user->newSubscription('default', 2283849)
                ->paymentSource(PaymentSource::hostedFields('hosted-fields-token'))
                ->create(idempotencyKey: 'retry-safely');
            self::fail('Expected the rate-limited request to fail.');
        } catch (RetryableBlueSnapOperation $exception) {
            self::assertStringContainsString('same idempotency key', $exception->getMessage());
            self::assertFalse($user->subscriptions()->exists());
        }
    }

    public function test_it_rolls_back_a_customer_when_the_subscription_response_is_incomplete(): void
    {
        $user = User::query()->create(['name' => 'Grace Hopper', 'email' => 'grace@example.com']);
        $this->http->queueJson(['vaultedShopperId' => 19574803]);

        try {
            $user->newSubscription('default', 2283849)
                ->paymentSource(PaymentSource::hostedFields('hosted-fields-token'))
                ->create();
            self::fail('Expected the incomplete response to fail.');
        } catch (IncompleteBlueSnapResponse) {
            self::assertFalse($user->subscriptions()->exists());
            self::assertFalse($user->blueSnapCustomer()->exists());
        }
    }
}
