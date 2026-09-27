<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $event_key
 * @property string $transaction_type
 * @property string|null $reference_number
 * @property string|null $subscription_bluesnap_id
 * @property string $status
 * @property int $attempts
 * @property array<string, mixed> $payload
 * @property \Illuminate\Support\Carbon $received_at
 * @property \Illuminate\Support\Carbon|null $processed_at
 * @property \Illuminate\Support\Carbon|null $failed_at
 * @property string|null $error
 */
class WebhookEvent extends Model
{
    public const string STATUS_RECEIVED = 'received';
    public const string STATUS_QUEUED = 'queued';
    public const string STATUS_PROCESSING = 'processing';
    public const string STATUS_PROCESSED = 'processed';
    public const string STATUS_IGNORED = 'ignored';
    public const string STATUS_FAILED = 'failed';

    protected $table = 'bluesnap_webhook_events';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'payload' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
