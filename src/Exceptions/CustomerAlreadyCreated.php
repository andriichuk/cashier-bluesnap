<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap\Exceptions;

use LogicException;
use Illuminate\Database\Eloquent\Model;

final class CustomerAlreadyCreated extends LogicException
{
    public static function forBillable(Model $billable): self
    {
        $key = $billable->getKey();

        return new self(sprintf(
            'The billable model [%s:%s] already has a BlueSnap customer.',
            $billable::class,
            is_int($key) || is_string($key) ? (string) $key : 'unknown',
        ));
    }
}
