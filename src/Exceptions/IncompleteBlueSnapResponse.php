<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap\Exceptions;

use RuntimeException;

final class IncompleteBlueSnapResponse extends RuntimeException
{
    public static function missing(string $field): self
    {
        return new self(sprintf('The BlueSnap response did not contain [%s].', $field));
    }
}
