<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap\Events;

use Andriichuk\CashierBlueSnap\WebhookEvent;

final readonly class WebhookHandled
{
    public function __construct(
        public WebhookEvent $webhook,
        public bool $stateChanged,
    ) {
    }
}
