# Changelog

## Production Startup Upgrade Pass

- Added `.env` driven configuration with production-safe defaults.
- Added `.env.example`.
- Added secure headers and configurable session cookie settings.
- Routed user session/dashboard/update and tournament create/list/get/join endpoints through the app controller/service layer.
- Added WhatsApp contact consent fields and E.164 validation.
- Added authorized opponent contact endpoint with server-generated WhatsApp message and audit event.
- Added contact availability and contact abuse reporting endpoints.
- Added match schedule proposal, confirmation, rejection, and retrieval endpoints.
- Added audit logs, match contact events, match schedules, user blocks, and contact reports migration.
- Added dashboard "My Next Match" action panel.
- Added WhatsApp and scheduling actions to tournament fixture rows.
- Added profile settings for WhatsApp contact preference and timezone.
- Added documentation for architecture, security, tournament rules, and payment integration.
- Added `.gitignore` entries for secrets, logs, cache, evidence, and uploaded result files.

## Remaining Future Improvements

- Complete wallet ledger and withdrawal services.
- Add payment provider interface and signed webhook processing.
- Move evidence storage outside public uploads and serve through an authorized controller.
- Add automated test runner and integration tests for tournament engines and privacy rules.
- Finish full guided tournament creation wizard and admin risk dashboard.
