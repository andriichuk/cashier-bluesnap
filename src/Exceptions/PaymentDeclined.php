<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap\Exceptions;

use RuntimeException;

final class PaymentDeclined extends RuntimeException
{
    /** @param array<mixed> $response */
    public function __construct(
        string $message,
        public readonly array $response,
    ) {
        parent::__construct($message);
    }

    /** @param array<mixed> $response */
    public static function fromResponse(array $response): self
    {
        $charge = $response['charge'] ?? null;
        $processing = is_array($charge)
            ? ($charge['processingInfo'] ?? [])
            : ($response['processingInfo'] ?? []);
        $message = is_array($processing) && is_string($processing['processingErrorDescription'] ?? null)
            ? $processing['processingErrorDescription']
            : 'BlueSnap declined the payment.';

        return new self($message, $response);
    }
}
