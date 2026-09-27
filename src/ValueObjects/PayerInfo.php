<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap\ValueObjects;

use Andriichuk\CashierBlueSnap\Exceptions\InvalidBillingPayload;

final readonly class PayerInfo
{
    /**
     * @param array<string, scalar|null> $additional
     */
    public function __construct(
        public string $firstName,
        public string $lastName,
        public ?string $email = null,
        public ?string $merchantShopperId = null,
        public array $additional = [],
    ) {
        if (trim($this->firstName) === '' || trim($this->lastName) === '') {
            throw InvalidBillingPayload::because('Payer first and last names are required.');
        }

        if ($this->email !== null && filter_var($this->email, FILTER_VALIDATE_EMAIL) === false) {
            throw InvalidBillingPayload::because('The payer email address is invalid.');
        }
    }

    public static function fromFullName(
        string $name,
        ?string $email = null,
        ?string $merchantShopperId = null,
    ): self {
        $parts = preg_split('/\s+/', trim($name), 2) ?: [];

        if (count($parts) !== 2) {
            throw InvalidBillingPayload::because(
                'A billable model without a vaulted shopper must provide both a first and last name, or call payer() explicitly.',
            );
        }

        return new self($parts[0], $parts[1], $email, $merchantShopperId);
    }

    /** @return array<string, scalar|null> */
    public function toArray(): array
    {
        return array_filter([
            ...$this->additional,
            'firstName' => trim($this->firstName),
            'lastName' => trim($this->lastName),
            'email' => $this->email,
            'merchantShopperId' => $this->merchantShopperId,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
