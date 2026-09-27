<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap\Tests\Unit;

use Andriichuk\CashierBlueSnap\Jobs\ProcessWebhook;
use Andriichuk\CashierBlueSnap\Tests\TestCase;
use Andriichuk\CashierBlueSnap\WebhookEvent;
use Illuminate\Support\Facades\Queue;

final class WebhookControllerTest extends TestCase
{
    public function test_it_accepts_the_empty_bluesnap_dashboard_probe(): void
    {
        $this->get('/bluesnap/webhook')->assertOk()->assertContent('');
    }

    public function test_it_rejects_unsigned_and_stale_deliveries(): void
    {
        $this->app['config']->set('cashier-bluesnap.webhooks.secret', 'security-key');
        $body = 'transactionType=CHARGE&referenceNumber=123';

        $this->postRaw($body)->assertUnauthorized();

        $timestamp = '2000-01-01 00:00:00.000';
        $this->postRaw($body, $timestamp, hash_hmac('sha256', $timestamp.$body, 'security-key'))
            ->assertUnauthorized();

        self::assertSame(0, WebhookEvent::query()->count());
    }

    public function test_it_durably_records_and_dispatches_each_raw_delivery_once(): void
    {
        Queue::fake();
        $this->app['config']->set('cashier-bluesnap.webhooks.secret', 'security-key');
        $body = 'transactionType=CHARGE&referenceNumber=123&invoiceAmount=29.99&currency=USD';
        $timestamp = gmdate('Y-m-d H:i:s').'.000';
        $signature = hash_hmac('sha256', $timestamp.$body, 'security-key');

        $this->postRaw($body, $timestamp, $signature)->assertOk()->assertContent('');
        $this->postRaw($body, $timestamp, $signature)->assertOk()->assertContent('');

        Queue::assertPushed(ProcessWebhook::class, 1);
        self::assertSame(1, WebhookEvent::query()->count());
        $webhook = WebhookEvent::query()->firstOrFail();
        self::assertSame(WebhookEvent::STATUS_QUEUED, $webhook->status);
        self::assertSame('CHARGE', $webhook->transaction_type);
        self::assertSame('123', $webhook->reference_number);
    }

    private function postRaw(string $body, string $timestamp = '', string $signature = ''): \Illuminate\Testing\TestResponse
    {
        return $this->call('POST', '/bluesnap/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            'HTTP_BLS_IPN_TIMESTAMP' => $timestamp,
            'HTTP_BLS_SIGNATURE' => $signature,
        ], $body);
    }
}
