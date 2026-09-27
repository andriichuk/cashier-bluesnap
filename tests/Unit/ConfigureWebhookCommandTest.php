<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap\Tests\Unit;

use Andriichuk\CashierBlueSnap\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;

final class ConfigureWebhookCommandTest extends TestCase
{
    public function test_it_sends_a_complete_webhook_configuration(): void
    {
        $this->http->queueJson([]);

        $exitCode = Artisan::call('bluesnap:webhook', [
            'url' => 'https://billing.example.com/bluesnap/webhook',
        ]);

        self::assertSame(0, $exitCode);
        self::assertCount(1, $this->http->requests);
        self::assertSame('POST', $this->http->requests[0]->getMethod());
        $payload = $this->http->requestJson();
        self::assertTrue($payload['ensureNotificationReceipt']);
        self::assertFalse($payload['receiveAffiliateNotifications']);
        self::assertSame(
            'https://billing.example.com/bluesnap/webhook',
            $payload['ipnDestinations'][0]['ipnUrl'],
        );
        self::assertTrue($payload['ipnDestinations'][0]['sendCancellation']);
        self::assertTrue($payload['ipnDestinations'][0]['sendRecurring']);
    }

    public function test_it_rejects_a_non_https_destination(): void
    {
        self::assertSame(2, Artisan::call('bluesnap:webhook', [
            'url' => 'http://billing.example.com/bluesnap/webhook',
        ]));
        self::assertCount(0, $this->http->requests);
    }
}
