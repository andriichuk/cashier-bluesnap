<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap;

use Andriichuk\CashierBlueSnap\Exceptions\InvalidBillingPayload;
use Andriichuk\CashierBlueSnap\Support\BlueSnapOperation;
use Andriichuk\CashierBlueSnap\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $type
 * @property string $bluesnap_id
 * @property string $plan_id
 * @property string $status
 * @property int $quantity
 * @property string|null $currency
 * @property string|null $recurring_amount
 * @property bool $auto_renew
 * @property \Illuminate\Support\Carbon|null $trial_ends_at
 * @property \Illuminate\Support\Carbon|null $next_charge_at
 * @property \Illuminate\Support\Carbon|null $ends_at
 * @property array<mixed>|null $raw_response
 */
class Subscription extends Model
{
    public const string STATUS_ACTIVE = 'ACTIVE';
    public const string STATUS_CANCELED = 'CANCELED';
    public const string STATUS_ON_HOLD = 'ON_HOLD';
    public const string STATUS_SUSPENDED = 'SUSPENDED';
    public const string STATUS_FINISHED = 'FINISHED';

    protected $table = 'bluesnap_subscriptions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'auto_renew' => 'boolean',
            'trial_ends_at' => 'datetime',
            'next_charge_at' => 'datetime',
            'ends_at' => 'datetime',
            'raw_response' => 'array',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Cashier::$transactionModel, 'subscription_bluesnap_id', 'bluesnap_id');
    }

    public function active(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function canceled(): bool
    {
        return $this->status === self::STATUS_CANCELED;
    }

    public function onHold(): bool
    {
        return $this->status === self::STATUS_ON_HOLD;
    }

    public function suspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function finished(): bool
    {
        return $this->status === self::STATUS_FINISHED;
    }

    public function onTrial(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
    }

    public function hasExpiredTrial(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isPast();
    }

    public function onGracePeriod(): bool
    {
        return $this->active()
            && ! $this->auto_renew
            && $this->ends_at !== null
            && $this->ends_at->isFuture();
    }

    public function recurring(): bool
    {
        return $this->active() && $this->auto_renew;
    }

    public function valid(): bool
    {
        return $this->active() || $this->onTrial() || $this->onGracePeriod();
    }

    public function recurringMoney(): ?Money
    {
        if ($this->recurring_amount === null || $this->currency === null) {
            return null;
        }

        return Money::of($this->recurring_amount, $this->currency);
    }

    public function refreshFromBlueSnap(): self
    {
        return $this->serialized(function (): self {
            $response = BlueSnapOperation::run(
                fn () => Cashier::client()->subscriptions()->retrieve($this->bluesnap_id),
            );

            return $this->persistBlueSnapState($response->json());
        });
    }

    public function swap(int|string $planId, ?int $quantity = null): self
    {
        $payload = ['planId' => (string) $planId];

        if ($quantity !== null) {
            $this->assertValidQuantity($quantity);
            $payload['quantity'] = $quantity;
        }

        return $this->serialized(function () use ($payload, $planId, $quantity): self {
            $response = BlueSnapOperation::run(
                fn () => Cashier::client()->subscriptions()->update($this->bluesnap_id, $payload),
            );

            $this->forceFill([
                'plan_id' => (string) $planId,
                'quantity' => $quantity ?? $this->quantity,
            ]);

            return $this->persistBlueSnapState($response->json());
        });
    }

    /** @return array<mixed> */
    public function previewSwap(int|string $planId, ?int $quantity = null): array
    {
        $changes = ['planid' => (string) $planId];

        if ($quantity !== null) {
            $this->assertValidQuantity($quantity);
            $changes['quantity'] = $quantity;
        }

        return BlueSnapOperation::run(
            fn () => Cashier::client()->subscriptions()->switchChargeAmount($this->bluesnap_id, $changes),
        )->json();
    }

    public function updateQuantity(int $quantity): self
    {
        $this->assertValidQuantity($quantity);

        return $this->serialized(fn (): self => $this->persistQuantity($quantity));
    }

    public function incrementQuantity(int $count = 1): self
    {
        $this->assertValidCount($count);

        return $this->serialized(
            fn (): self => $this->persistQuantity($this->quantity + $count),
        );
    }

    public function decrementQuantity(int $count = 1): self
    {
        $this->assertValidCount($count);

        return $this->serialized(function () use ($count): self {
            $quantity = $this->quantity - $count;
            $this->assertValidQuantity($quantity);

            return $this->persistQuantity($quantity);
        });
    }

    public function cancel(): self
    {
        return $this->serialized(function (): self {
            $response = BlueSnapOperation::run(
                fn () => Cashier::client()->subscriptions()->cancelAtPeriodEnd($this->bluesnap_id),
            );

            $this->auto_renew = false;
            $this->ends_at = $this->next_charge_at;

            return $this->persistBlueSnapState($response->json());
        });
    }

    public function cancelNow(): self
    {
        return $this->serialized(function (): self {
            $response = BlueSnapOperation::run(
                fn () => Cashier::client()->subscriptions()->cancel($this->bluesnap_id),
            );

            $this->status = self::STATUS_CANCELED;
            $this->auto_renew = false;
            $this->ends_at = Carbon::now();

            return $this->persistBlueSnapState($response->json());
        });
    }

    public function resume(): self
    {
        return $this->serialized(function (): self {
            $response = BlueSnapOperation::run(
                fn () => Cashier::client()->subscriptions()->renew($this->bluesnap_id),
            );

            $this->auto_renew = true;
            $this->ends_at = null;

            return $this->persistBlueSnapState($response->json());
        });
    }

    /** @param array<mixed> $data */
    public function syncFromBlueSnap(array $data): self
    {
        return $this->serialized(fn (): self => $this->persistBlueSnapState($data));
    }

    /** @param array<mixed> $data */
    private function persistBlueSnapState(array $data): self
    {
        $attributes = [
            'raw_response' => $data,
        ];

        foreach (['subscriptionId' => 'bluesnap_id', 'planId' => 'plan_id'] as $remote => $local) {
            if (is_int($data[$remote] ?? null) || is_string($data[$remote] ?? null)) {
                $attributes[$local] = (string) $data[$remote];
            }
        }

        foreach (['status' => 'status', 'currency' => 'currency'] as $remote => $local) {
            if (is_string($data[$remote] ?? null)) {
                $attributes[$local] = $data[$remote];
            }
        }

        if (is_numeric($data['quantity'] ?? null)) {
            $attributes['quantity'] = (int) $data['quantity'];
        }

        $recurringAmount = Money::normalizeApiAmount($data['recurringChargeAmount'] ?? null);

        if ($recurringAmount !== null) {
            $attributes['recurring_amount'] = $recurringAmount;
        }

        if (is_bool($data['autoRenew'] ?? null)) {
            $attributes['auto_renew'] = $data['autoRenew'];
        }

        if (isset($data['nextChargeDate']) && is_string($data['nextChargeDate'])) {
            $attributes['next_charge_at'] = Carbon::parse($data['nextChargeDate']);
        }

        if ($this->trial_ends_at === null && isset($data['trialPeriodDays']) && is_numeric($data['trialPeriodDays'])) {
            $attributes['trial_ends_at'] = Carbon::now()->addDays((int) $data['trialPeriodDays']);
        }

        $status = is_string($attributes['status'] ?? null) ? $attributes['status'] : $this->status;
        $autoRenew = is_bool($attributes['auto_renew'] ?? null) ? $attributes['auto_renew'] : $this->auto_renew;
        $nextChargeAt = $attributes['next_charge_at'] ?? $this->next_charge_at;

        if ($status === self::STATUS_CANCELED) {
            $attributes['ends_at'] = $this->ends_at ?? Carbon::now();
        } elseif (! $autoRenew && $nextChargeAt !== null) {
            $attributes['ends_at'] = $nextChargeAt;
        } elseif ($autoRenew) {
            $attributes['ends_at'] = null;
        }

        $this->forceFill($attributes)->save();

        return $this->refresh();
    }

    private function assertValidQuantity(int $quantity): void
    {
        if ($quantity < 1) {
            throw InvalidBillingPayload::because('Subscription quantity must be at least 1.');
        }
    }

    private function assertValidCount(int $count): void
    {
        if ($count < 1) {
            throw InvalidBillingPayload::because('The quantity adjustment count must be at least 1.');
        }
    }

    private function persistQuantity(int $quantity): self
    {
        $response = BlueSnapOperation::run(
            fn () => Cashier::client()->subscriptions()->update($this->bluesnap_id, [
                'planId' => $this->plan_id,
                'quantity' => $quantity,
            ]),
        );

        $this->quantity = $quantity;

        return $this->persistBlueSnapState($response->json());
    }

    /**
     * @param callable(): self $operation
     */
    private function serialized(callable $operation): self
    {
        return $this->getConnection()->transaction(function () use ($operation): self {
            $this->newQuery()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->refresh();

            return $operation();
        });
    }
}
