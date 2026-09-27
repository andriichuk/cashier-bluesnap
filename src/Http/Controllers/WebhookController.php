<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap\Http\Controllers;

use Andriichuk\CashierBlueSnap\Cashier;
use Andriichuk\CashierBlueSnap\Contracts\WebhookSignatureVerifier;
use Andriichuk\CashierBlueSnap\Jobs\ProcessWebhook;
use Andriichuk\CashierBlueSnap\WebhookEvent;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final readonly class WebhookController
{
    public function __construct(
        private WebhookSignatureVerifier $signatureVerifier,
        private Dispatcher $bus,
        private Repository $config,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        // BlueSnap's dashboard tests destinations using an empty GET request.
        if ($request->isMethod('GET')) {
            return response('', 200);
        }

        $rawBody = $request->getContent();
        $timestamp = $request->headers->get('Bls-Ipn-Timestamp') ?? '';
        $signature = $request->headers->get('Bls-Signature') ?? '';
        $verify = $this->config->get('cashier-bluesnap.webhooks.verify_signature', true);

        if ($rawBody === '') {
            return response('', 400);
        }

        if ($verify !== false && ! $this->signatureVerifier->verify($timestamp, $signature, $rawBody)) {
            return response('', 401);
        }

        parse_str($rawBody, $payload);

        if (! is_string($payload['transactionType'] ?? null) || $payload['transactionType'] === '') {
            return response('', 400);
        }

        $model = Cashier::$webhookEventModel;
        /** @var WebhookEvent $webhook */
        $webhook = $model::query()->firstOrCreate(
            ['event_key' => hash('sha256', $rawBody)],
            [
                'transaction_type' => strtoupper($payload['transactionType']),
                'reference_number' => $this->scalarString($payload['referenceNumber'] ?? null),
                'subscription_bluesnap_id' => $this->scalarString($payload['subscriptionId'] ?? null),
                'status' => WebhookEvent::STATUS_RECEIVED,
                'attempts' => 0,
                'payload' => $payload,
                'received_at' => now(),
            ],
        );

        if ($webhook->wasRecentlyCreated || $webhook->status === WebhookEvent::STATUS_RECEIVED) {
            $webhookId = $webhook->getKey();

            if (! is_int($webhookId) && ! is_string($webhookId)) {
                return response('', 500);
            }

            $job = new ProcessWebhook($webhookId);
            $connection = $this->config->get('cashier-bluesnap.webhooks.queue_connection');
            $queue = $this->config->get('cashier-bluesnap.webhooks.queue');

            if (is_string($connection) && $connection !== '') {
                $job->onConnection($connection);
            }

            if (is_string($queue) && $queue !== '') {
                $job->onQueue($queue);
            }

            $this->bus->dispatch($job);
            $model::query()
                ->whereKey($webhookId)
                ->where('status', WebhookEvent::STATUS_RECEIVED)
                ->update(['status' => WebhookEvent::STATUS_QUEUED]);
        }

        return response('', 200);
    }

    private function scalarString(mixed $value): ?string
    {
        return is_int($value) || is_string($value) ? (string) $value : null;
    }
}
