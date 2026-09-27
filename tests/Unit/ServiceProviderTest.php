<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap\Tests\Unit;

use Andriichuk\BlueSnap\BlueSnapClient;
use Andriichuk\BlueSnap\Environment;
use Andriichuk\CashierBlueSnap\Tests\TestCase;

final class ServiceProviderTest extends TestCase
{
    public function test_it_registers_the_configured_sdk_client(): void
    {
        $this->app->forgetInstance(BlueSnapClient::class);

        $client = $this->app->make(BlueSnapClient::class);

        self::assertSame('merchant', $client->configuration->username);
        self::assertSame('secret', $client->configuration->password);
        self::assertSame(Environment::Sandbox, $client->configuration->environment);
    }
}
