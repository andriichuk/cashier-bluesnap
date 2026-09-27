<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap\ValueObjects;

use Andriichuk\CashierBlueSnap\Exceptions\InvalidBillingPayload;
use JsonSerializable;

final readonly class Money implements JsonSerializable
{
    private function __construct(
        public string $amount,
        public string $currency,
    ) {
    }

    public static function of(int|string $amount, string $currency): self
    {
        $normalizedAmount = self::normalizeAmount($amount);
        $normalizedCurrency = strtoupper(trim($currency));

        if (preg_match('/^[A-Z]{3}$/', $normalizedCurrency) !== 1) {
            throw InvalidBillingPayload::because('Currency must be a three-letter ISO 4217 code.');
        }

        return new self($normalizedAmount, $normalizedCurrency);
    }

    /** @return array{amount: string, currency: string} */
    public function jsonSerialize(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
        ];
    }

    public static function normalizeAmount(int|string $amount): string
    {
        $value = trim((string) $amount);

        if (preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,8})?$/', $value) !== 1) {
            throw InvalidBillingPayload::because(
                'Money amounts must be non-negative decimal strings or integers with at most eight decimal places.',
            );
        }

        return $value;
    }

    public static function normalizeApiAmount(mixed $amount): ?string
    {
        if (is_int($amount) || is_string($amount)) {
            return self::normalizeAmount($amount);
        }

        if (! is_float($amount) || ! is_finite($amount) || $amount < 0) {
            return null;
        }

        $normalized = rtrim(rtrim(number_format($amount, 8, '.', ''), '0'), '.');

        return self::normalizeAmount($normalized);
    }
}
