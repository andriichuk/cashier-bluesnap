<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap\Exceptions;

use LogicException;

final class SubscriptionAlreadyCreated extends LogicException
{
    public static function forType(string $type): self
    {
        return new self(sprintf('The billable model already has a BlueSnap subscription of type [%s].', $type));
    }
}
