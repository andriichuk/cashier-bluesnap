<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap\Exceptions;

use InvalidArgumentException;

final class InvalidBillingPayload extends InvalidArgumentException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
