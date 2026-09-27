<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap\Tests\Unit;

use Andriichuk\CashierBlueSnap\Exceptions\InvalidBillingPayload;
use Andriichuk\CashierBlueSnap\Tests\TestCase;
use Andriichuk\CashierBlueSnap\ValueObjects\Money;
use Andriichuk\CashierBlueSnap\ValueObjects\PayerInfo;
use Andriichuk\CashierBlueSnap\ValueObjects\PaymentSource;

final class ValueObjectsTest extends TestCase
{
    public function test_money_normalizes_currency_without_using_floats(): void
    {
        $money = Money::of('29.9900', 'usd');

        self::assertSame('29.9900', $money->amount);
        self::assertSame('USD', $money->currency);
        self::assertSame(['amount' => '29.9900', 'currency' => 'USD'], $money->jsonSerialize());
    }

    public function test_money_rejects_unsafe_decimal_values(): void
    {
        $this->expectException(InvalidBillingPayload::class);

        Money::of('1.2e3', 'USD');
    }

    public function test_payer_info_validates_names_and_email(): void
    {
        $payer = PayerInfo::fromFullName('Ada Lovelace', 'ada@example.com', '42');

        self::assertSame([
            'firstName' => 'Ada',
            'lastName' => 'Lovelace',
            'email' => 'ada@example.com',
            'merchantShopperId' => '42',
        ], $payer->toArray());
    }

    public function test_hosted_fields_tokens_are_top_level_subscription_properties(): void
    {
        self::assertSame(
            ['pfToken' => 'token'],
            PaymentSource::hostedFields('token')->toSubscriptionPayload(),
        );

        self::assertSame(
            ['paymentSource' => ['wallet' => ['walletType' => 'APPLE_PAY']]],
            PaymentSource::fromArray(['wallet' => ['walletType' => 'APPLE_PAY']])->toSubscriptionPayload(),
        );
    }
}
