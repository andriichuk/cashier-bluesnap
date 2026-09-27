<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap\Jobs;

use Andriichuk\CashierBlueSnap\Cashier;
use Andriichuk\CashierBlueSnap\Events\WebhookFailed;
use Andriichuk\CashierBlueSnap\Events\WebhookHandled;
use Andriichuk\CashierBlueSnap\Events\WebhookReceived;
use Andriichuk\CashierBlueSnap\WebhookEvent;
use Andriichuk\CashierBlueSnap\Webhooks\WebhookProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;

final class ProcessWebhook implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $uniqueFor = 3600;

    public function __construct(public int|string $webhookId)
    {
    }

    public function uniqueId(): string
    {
        return (string) $this->webhookId;
    }

    public function handle(WebhookProcessor $processor, Dispatcher $events): void
    {
        $model = Cashier::$webhookEventModel;
        /** @var WebhookEvent $webhook */
        $webhook = $model::query()->findOrFail($this->webhookId);

        if (in_array($webhook->status, [WebhookEvent::STATUS_PROCESSED, WebhookEvent::STATUS_IGNORED], true)) {
            return;
        }

        $webhook->forceFill([
            'status' => WebhookEvent::STATUS_PROCESSING,
            'attempts' => $webhook->attempts + 1,
            'failed_at' => null,
            'error' => null,
        ])->save();

        try {
            $events->dispatch(new WebhookReceived($webhook));
            $changed = $processor->process($webhook);
            $webhook->forceFill([
                'status' => $changed ? WebhookEvent::STATUS_PROCESSED : WebhookEvent::STATUS_IGNORED,
                'processed_at' => now(),
            ])->save();
            $events->dispatch(new WebhookHandled($webhook->refresh(), $changed));
        } catch (Throwable $exception) {
            $webhook->forceFill([
                'status' => WebhookEvent::STATUS_FAILED,
                'failed_at' => now(),
                'error' => substr($exception->getMessage(), 0, 65535),
            ])->save();
            $events->dispatch(new WebhookFailed($webhook->refresh(), $exception));

            throw $exception;
        }
    }
}
