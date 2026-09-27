<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap\Support;

use Andriichuk\CashierBlueSnap\Exceptions\InvalidBillingPayload;
use DateTimeImmutable;

final class SubscriptionPayloadValidator
{
    /** @param array<string, mixed> $payload */
    public static function validate(array $payload, bool $hasVaultedShopper): void
    {
        if (isset($payload['pfToken']) && isset($payload['paymentSource'])) {
            throw InvalidBillingPayload::because('Use either pfToken or paymentSource, not both.');
        }

        if ($hasVaultedShopper && isset($payload['payerInfo'])) {
            throw InvalidBillingPayload::because('payerInfo cannot be sent together with vaultedShopperId.');
        }

        if (! $hasVaultedShopper) {
            self::validatePayer($payload['payerInfo'] ?? null);

            if (! isset($payload['pfToken']) && ! isset($payload['paymentSource'])) {
                throw InvalidBillingPayload::because(
                    'A new BlueSnap shopper subscription requires a Hosted Payment Fields token or paymentSource.',
                );
            }
        }

        if (isset($payload['pfToken']) && (! is_string($payload['pfToken']) || trim($payload['pfToken']) === '')) {
            throw InvalidBillingPayload::because('The Hosted Payment Fields token must be a non-empty string.');
        }

        if (isset($payload['paymentSource']) && (! is_array($payload['paymentSource']) || $payload['paymentSource'] === [])) {
            throw InvalidBillingPayload::because('paymentSource must be a non-empty object.');
        }

        if (is_array($payload['paymentSource'] ?? null) && isset($payload['paymentSource']['pfToken'])) {
            throw InvalidBillingPayload::because(
                'For Create Subscription, the Hosted Payment Fields pfToken must be a top-level property.',
            );
        }

        if (isset($payload['nextChargeDate'])) {
            self::validateDate($payload['nextChargeDate']);
        }
    }

    private static function validatePayer(mixed $payer): void
    {
        if (! is_array($payer)) {
            throw InvalidBillingPayload::because('payerInfo is required when no vaulted shopper exists.');
        }

        foreach (['firstName', 'lastName'] as $field) {
            if (! is_string($payer[$field] ?? null) || trim($payer[$field]) === '') {
                throw InvalidBillingPayload::because(sprintf('payerInfo.%s is required.', $field));
            }
        }
    }

    private static function validateDate(mixed $date): void
    {
        if (! is_string($date)) {
            throw InvalidBillingPayload::because('The next charge date must use YYYY-MM-DD format.');
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = DateTimeImmutable::getLastErrors();

        if ($parsed === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $parsed->format('Y-m-d') !== $date) {
            throw InvalidBillingPayload::because('The next charge date must be a valid YYYY-MM-DD date.');
        }
    }
}
