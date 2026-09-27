<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap\Concerns;

use Andriichuk\CashierBlueSnap\Cashier;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** @mixin \Illuminate\Database\Eloquent\Model */
trait ManagesBlueSnapTransactions
{
    /** @return MorphMany<\Andriichuk\CashierBlueSnap\Transaction, $this> */
    public function blueSnapTransactions(): MorphMany
    {
        return $this->morphMany(Cashier::$transactionModel, 'billable');
    }
}
