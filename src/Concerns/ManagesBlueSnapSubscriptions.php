<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap\Concerns;

use Andriichuk\CashierBlueSnap\Cashier;
use Andriichuk\CashierBlueSnap\Subscription;
use Andriichuk\CashierBlueSnap\SubscriptionBuilder;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** @mixin \Illuminate\Database\Eloquent\Model */
trait ManagesBlueSnapSubscriptions
{
    /** @return MorphMany<Subscription, $this> */
    public function subscriptions(): MorphMany
    {
        return $this->morphMany(Cashier::$subscriptionModel, 'billable');
    }

    public function subscription(string $type = 'default'): ?Subscription
    {
        /** @var Subscription|null */
        return $this->subscriptions()->where('type', $type)->first();
    }

    public function subscribed(string $type = 'default', int|string|null $planId = null): bool
    {
        $subscription = $this->subscription($type);

        if ($subscription === null || ! $subscription->valid()) {
            return false;
        }

        return $planId === null || $subscription->plan_id === (string) $planId;
    }

    public function newSubscription(string $type, int|string $planId): SubscriptionBuilder
    {
        return new SubscriptionBuilder(
            $this,
            $type,
            (string) $planId,
            $this->blueSnapName(),
            $this->blueSnapEmail(),
        );
    }
}
