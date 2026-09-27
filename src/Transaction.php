<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $bluesnap_id
 * @property string|null $subscription_bluesnap_id
 * @property string $type
 * @property string $status
 * @property string|null $amount
 * @property string|null $currency
 * @property \Illuminate\Support\Carbon|null $billed_at
 * @property array<mixed>|null $raw_response
 */
class Transaction extends Model
{
    protected $table = 'bluesnap_transactions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'billed_at' => 'datetime',
            'raw_response' => 'array',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function billable(): MorphTo
    {
        return $this->morphTo();
    }
}
