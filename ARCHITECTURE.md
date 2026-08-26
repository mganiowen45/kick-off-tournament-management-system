# Architecture

KICKOFF remains a plain PHP 8.2 application with MySQL/MariaDB, PDO prepared statements, and vanilla JavaScript.

The active backend path is:

- `bootstrap.php` loads config, sessions, autoloading, and security headers.
- `app/Core` contains request, response, database, auth, session, and CSRF primitives.
- `app/Controllers` handles HTTP input/output.
- `app/Services` owns tournament, result, notification, payment, catalog, finance, WhatsApp, and scheduling rules.
- `api/*` files are thin entry points into controllers/services.
- `database/migrations` contains incremental schema changes.

Key product modules:

- `TournamentService` validates creation/joining, private visibility, profile compatibility, creator/admin permission split, cancellation requests, and paid reservations.
- `TournamentEngine` owns 1V1 Best-of-3 series, stable Full Knockout progression, and Group Stage + Knockout qualification.
- `ResultService` is the authoritative result verification path and disallows unresolved draws outside group-stage matches.
- `PaymentService` and `ClickPesaService` isolate checkout/webhook architecture.
- `TournamentCatalogService` exposes games, platforms, covers, final type labels, funding labels, and settings.
- `api/users/session.php` is the canonical session/CSRF bootstrap endpoint for frontend requests; unsafe same-origin fetches use `X-CSRF-Token`.
- `profile_setup.html` is a standalone onboarding gate. Player pages use `requirePlayerPage()` and modern competitive APIs use `Auth::requireCompletedPlayerProfile()`.

The UI keeps the existing esports shell in `shared.css`, with theme tokens for Esport, Light, and Midnight.
