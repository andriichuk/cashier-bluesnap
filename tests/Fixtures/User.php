<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap\Tests\Fixtures;

use Andriichuk\CashierBlueSnap\Billable;
use Illuminate\Database\Eloquent\Model;

final class User extends Model
{
    use Billable;

    protected $guarded = [];
}
