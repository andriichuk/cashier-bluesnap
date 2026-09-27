<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap\Tests\Unit;

use Andriichuk\CashierBlueSnap\Contracts\WebhookSignatureVerifier;
use Andriichuk\CashierBlueSnap\Tests\TestCase;

final class WebhookSignatureVerifierTest extends TestCase
{
    public function test_it_verifies_bluesnaps_hmac_over_the_exact_timestamp_and_body(): void
    {
        $this->app['config']->set('cashier-bluesnap.webhooks.secret', 'security-key');
        $timestamp = gmdate('Y-m-d H:i:s').'.000';
        $body = 'transactionType=CHARGE&referenceNumber=123&name=Ada+Lovelace';
        $signature = hash_hmac('sha256', $timestamp.$body, 'security-key');

        $verifier = $this->app->make(WebhookSignatureVerifier::class);

        self::assertTrue($verifier->verify($timestamp, $signature, $body));
        self::assertFalse($verifier->verify($timestamp, $signature, $body.'&changed=true'));
    }

    public function test_it_rejects_stale_or_missing_security_headers(): void
    {
        $this->app['config']->set('cashier-bluesnap.webhooks.secret', 'security-key');
        $body = 'transactionType=CHARGE';
        $timestamp = '2000-01-01 00:00:00.000';
        $signature = hash_hmac('sha256', $timestamp.$body, 'security-key');
        $verifier = $this->app->make(WebhookSignatureVerifier::class);

        self::assertFalse($verifier->verify($timestamp, $signature, $body));
        self::assertFalse($verifier->verify('', '', $body));
    }
}
