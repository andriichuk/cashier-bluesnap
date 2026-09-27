<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap\Webhooks;

use Andriichuk\CashierBlueSnap\Contracts\WebhookSignatureVerifier;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Config\Repository;

final readonly class HmacWebhookSignatureVerifier implements WebhookSignatureVerifier
{
    public function __construct(private Repository $config)
    {
    }

    public function verify(string $timestamp, string $signature, string $rawBody): bool
    {
        $secret = $this->config->get('cashier-bluesnap.webhooks.secret');

        if (! is_string($secret) || $secret === '' || $timestamp === '' || $signature === '') {
            return false;
        }

        if (! $this->timestampIsFresh($timestamp)) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.$rawBody, $secret);

        return hash_equals($expected, strtolower($signature));
    }

    private function timestampIsFresh(string $timestamp): bool
    {
        $tolerance = $this->config->get('cashier-bluesnap.webhooks.timestamp_tolerance', 300);

        if (! is_int($tolerance) || $tolerance < 1) {
            return true;
        }

        $parsed = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s.v',
            $timestamp,
            new DateTimeZone('UTC'),
        );
        $errors = DateTimeImmutable::getLastErrors();

        if ($parsed === false || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return false;
        }

        return abs(time() - $parsed->getTimestamp()) <= $tolerance;
    }
}
