<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap\Events;

use Andriichuk\CashierBlueSnap\WebhookEvent;
use Throwable;

final readonly class WebhookFailed
{
    public function __construct(
        public WebhookEvent $webhook,
        public Throwable $exception,
    ) {
    }
}
