<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap\Tests\Unit;

use Andriichuk\CashierBlueSnap\Exceptions\CustomerAlreadyCreated;
use Andriichuk\CashierBlueSnap\Tests\Fixtures\User;
use Andriichuk\CashierBlueSnap\Tests\TestCase;

final class CustomerTest extends TestCase
{
    public function test_it_creates_and_stores_a_vaulted_shopper(): void
    {
        $user = User::query()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);
        $this->http->queueJson([
            'vaultedShopperId' => 19574802,
            'firstName' => 'Ada',
            'lastName' => 'Lovelace',
        ]);

        $customer = $user->createAsBlueSnapCustomer(['pfToken' => 'hosted-fields-token'], 'customer-1');

        self::assertSame('19574802', $customer->vaulted_shopper_id);
        self::assertSame('Ada Lovelace', $customer->name);
        self::assertSame('ada@example.com', $customer->email);
        self::assertTrue($user->hasBlueSnapCustomer());
        self::assertSame('customer-1', $this->http->requests[0]->getHeaderLine('Idempotency-Key'));
        self::assertSame([
            'firstName' => 'Ada',
            'lastName' => 'Lovelace',
            'email' => 'ada@example.com',
            'merchantShopperId' => (string) $user->getKey(),
            'pfToken' => 'hosted-fields-token',
        ], $this->http->requestJson());
    }

    public function test_it_rejects_a_second_customer(): void
    {
        $user = User::query()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);
        $user->blueSnapCustomer()->create(['vaulted_shopper_id' => '1']);

        $this->expectException(CustomerAlreadyCreated::class);

        $user->createAsBlueSnapCustomer();
    }
}
