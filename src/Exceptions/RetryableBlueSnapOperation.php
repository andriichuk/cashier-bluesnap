<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap\Exceptions;

use RuntimeException;
use Throwable;

final class RetryableBlueSnapOperation extends RuntimeException
{
    public static function fromException(Throwable $exception): self
    {
        return new self(
            'The BlueSnap operation failed temporarily and may be retried with the same idempotency key.',
            previous: $exception,
        );
    }
}
