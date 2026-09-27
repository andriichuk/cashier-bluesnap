<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $vaulted_shopper_id
 * @property string|null $name
 * @property string|null $email
 * @property \Illuminate\Support\Carbon|null $trial_ends_at
 * @property array<mixed>|null $raw_response
 */
class Customer extends Model
{
    protected $table = 'bluesnap_customers';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'raw_response' => 'array',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function billable(): MorphTo
    {
        return $this->morphTo();
    }
}
