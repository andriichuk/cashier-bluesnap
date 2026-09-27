# SDK endpoint requirements

The Cashier package delegates outbound HTTP calls to [`andriichuk/bluesnap-php-sdk`](https://github.com/andriichuk/bluesnap-php-sdk). The SDK already exposes every outbound endpoint planned for the initial card-subscription implementation.

| Laravel feature | SDK operation | Package status |
| --- | --- | --- |
| Hosted Payment Fields token | `paymentFieldsTokens()->create()` | Planned UI helper |
| Saved-card 3-D Secure prefill | `paymentFieldsTokens()->prefill()` | Planned UI helper |
| Customer create/retrieve/update/delete | `vaultedShoppers()` | Create/update/delete implemented |
| Plan create/retrieve/list/update/status | `plans()` | Available through SDK |
| Subscription create/retrieve/list/update | `subscriptions()` | Core lifecycle implemented |
| Plan-switch charge preview | `subscriptions()->switchChargeAmount()` | Implemented as `previewSwap()` |
| Subscription charge history/resolve | `subscriptions()->charges()`, `chargeByTransactionId()` | Planned synchronization |
| Sandbox renewal simulation | `subscriptions()->simulate()` | Available through SDK |
| Merchant-managed subscriptions | `merchantManagedSubscriptions()` | Available; Laravel abstraction deferred |
| Charge/authorize/capture/void | `transactions()` | Planned one-time charge abstraction |
| Transaction retrieve/reconcile | `transactions()->retrieve*()` | Planned synchronization |
| Full/partial refund and pending-refund cancellation | `transactions()->refund*()` | Planned refund abstraction |
| Webhook configuration | `webhookConfigurations()` | Available; inbound receiver planned |

Inbound webhooks do not use an SDK endpoint. The Laravel package must receive BlueSnap's URL-encoded notifications, verify their security headers, deduplicate delivery, persist the event, and update local models transactionally.

BlueSnap has no Paddle-style hosted customer portal endpoint. Any billing portal or invoice presentation must be implemented locally from vaulted-shopper, subscription, charge, and transaction data.
