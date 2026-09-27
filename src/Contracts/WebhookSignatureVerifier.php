<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap\Contracts;

interface WebhookSignatureVerifier
{
    public function verify(string $timestamp, string $signature, string $rawBody): bool;
}
