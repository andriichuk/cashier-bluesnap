<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap\Support;

use Andriichuk\BlueSnap\Exception\ApiException;
use Andriichuk\BlueSnap\Exception\RateLimitException;
use Andriichuk\BlueSnap\Exception\TransportException;
use Andriichuk\CashierBlueSnap\Exceptions\PaymentDeclined;
use Andriichuk\CashierBlueSnap\Exceptions\RetryableBlueSnapOperation;

final class BlueSnapOperation
{
    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public static function run(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (RateLimitException|TransportException $exception) {
            throw RetryableBlueSnapOperation::fromException($exception);
        } catch (ApiException $exception) {
            if ($exception->statusCode >= 500) {
                throw RetryableBlueSnapOperation::fromException($exception);
            }

            throw $exception;
        }
    }

    /** @param array<mixed> $response */
    public static function ensurePaymentSucceeded(array $response): void
    {
        $charge = $response['charge'] ?? null;
        $processing = is_array($charge)
            ? ($charge['processingInfo'] ?? null)
            : ($response['processingInfo'] ?? null);

        if (! is_array($processing)) {
            return;
        }

        $status = $processing['processingStatus'] ?? $processing['status'] ?? null;

        if (is_string($status) && strtoupper($status) === 'FAIL') {
            throw PaymentDeclined::fromResponse($response);
        }
    }
}
