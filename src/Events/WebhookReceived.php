<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap\Events;

use Andriichuk\CashierBlueSnap\WebhookEvent;

final readonly class WebhookReceived
{
    public function __construct(public WebhookEvent $webhook)
    {
    }
}
