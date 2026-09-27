# Changelog

All notable changes will be documented in this file.

## Unreleased

- Scaffold Laravel 13 package integration for PHP 8.5.
- Add polymorphic customer, subscription, and transaction models and migrations.
- Add the `Billable` trait and vaulted-shopper lifecycle operations.
- Add subscription creation, synchronization, plan swaps, quantity changes, cancellation, renewal, and status helpers.
- Add Laravel integration tests, static analysis, documentation, and CI.
- Correct Hosted Payment Fields subscription requests to use top-level `pfToken`.
- Add typed money, payer, and payment-source value objects and payload validation.
- Add transaction boundaries and row locks around local customer and subscription changes.
- Add declined-payment, retryable-operation, duplicate-subscription, and invalid-payload exceptions.
- Add authenticated, replay-resistant, idempotent BlueSnap webhook ingestion.
- Add durable webhook event storage, queued processing, lifecycle events, and retry diagnostics.
- Synchronize core subscription and transaction state from webhook deliveries.
- Add the `bluesnap:webhook` configuration command.
