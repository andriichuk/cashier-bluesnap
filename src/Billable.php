<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap;

use Andriichuk\CashierBlueSnap\Concerns\ManagesBlueSnapCustomers;
use Andriichuk\CashierBlueSnap\Concerns\ManagesBlueSnapSubscriptions;
use Andriichuk\CashierBlueSnap\Concerns\ManagesBlueSnapTransactions;

/** @mixin \Illuminate\Database\Eloquent\Model */
trait Billable
{
    use ManagesBlueSnapCustomers;
    use ManagesBlueSnapSubscriptions;
    use ManagesBlueSnapTransactions;
}
