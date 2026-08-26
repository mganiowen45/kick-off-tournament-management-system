# Payment Integration

KICKOFF uses a ClickPesa-ready architecture. The old wallet remains disabled and must not be used as fake money.

Implemented:

- `payments` records with unique order references.
- Paid tournament join reservations.
- Hosted checkout creation through `ClickPesaService`.
- Webhook endpoint: `api/payments/clickpesa_webhook.php`.
- Duplicate webhook protection in `payment_webhook_events`.
- Financial ledger entries after trusted backend confirmation.
- Refund and payout tables for admin workflows.

Important rule: a browser redirect is never proof of payment. Tournament participation is confirmed only after backend confirmation.

ClickPesa docs verified:

- Hosted Checkout generates a checkout URL via `POST https://api.clickpesa.com/third-parties/checkout-link/generate-checkout-url`.
- The request includes amount, order reference, currency, customer data, description, and optional callback URL.
- Webhooks send events such as `PAYMENT RECEIVED` and `PAYMENT FAILED`, and payloads can include checksum fields.

Still required from the project owner:

- Live ClickPesa application credentials.
- The exact checksum/webhook verification configuration enabled in the ClickPesa dashboard.
- Production webhook URL registration.
- Reconciliation and payout operating procedures.

Environment:

```env
PAYMENT_MODE=disabled
PAYMENT_PROVIDER=clickpesa
CLICKPESA_API_KEY=
CLICKPESA_API_SECRET=
CLICKPESA_WEBHOOK_SECRET=
DEFAULT_CURRENCY=TZS
```

Use `PAYMENT_MODE=sandbox` for local development. Live webhook acceptance currently fails closed until the checksum method is configured exactly.
