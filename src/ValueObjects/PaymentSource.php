<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap\ValueObjects;

use Andriichuk\CashierBlueSnap\Exceptions\InvalidBillingPayload;

final readonly class PaymentSource
{
    /**
     * @param array<string, mixed> $payload
     */
    private function __construct(
        private array $payload,
        private bool $hostedFields,
    ) {
    }

    public static function hostedFields(string $token): self
    {
        $token = trim($token);

        if ($token === '') {
            throw InvalidBillingPayload::because('The Hosted Payment Fields token cannot be empty.');
        }

        return new self(['pfToken' => $token], true);
    }

    /** @param array<string, mixed> $paymentSource */
    public static function fromArray(array $paymentSource): self
    {
        if ($paymentSource === []) {
            throw InvalidBillingPayload::because('The BlueSnap payment source cannot be empty.');
        }

        return new self($paymentSource, false);
    }

    /** @return array<string, mixed> */
    public function toSubscriptionPayload(): array
    {
        return $this->hostedFields ? $this->payload : ['paymentSource' => $this->payload];
    }
}
