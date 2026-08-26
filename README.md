# KICKOFF

KICKOFF is a plain-PHP 8.2+ competitive gaming tournament platform for XAMPP/MySQL with vanilla HTML, CSS, and JavaScript. The accepted dark esports UI remains the default Esport theme.

## Local Installation

Fresh install:

1. Create the database named in `.env` or `config/config.php` (`kick_off` by default).
2. Import `database/schema.sql`. This is the complete current schema for a new KICKOFF installation.
3. Copy `.env.example` to `.env` when present, or create `.env`, and set database credentials.
4. Open `http://localhost/kickoff5`.

Upgrade from an older local dump:

1. Back up the database.
2. Import the older base only if this is an existing legacy environment.
3. Run migrations in order:
   - `database/migrations/001_backend_refactor.sql`
   - `database/migrations/002_trust_scheduling_whatsapp.sql`
   - `database/migrations/003_final_kickoff_model.sql`
   - `database/migrations/004_correction_cleanup.sql`
   - `database/migrations/005_supported_game_images_and_onboarding_guard.sql`
   - `database/migrations/006_cover_paths_and_auto_start.sql`

`kickoff.sql` is retained as a legacy/sample dump. Do not use it as the fresh-install source for the current application.

## Final Tournament Types

KICKOFF now supports exactly:

- `1v1` - 1V1 Tournament, exactly 2 players, true Best-of-3 series.
- `full_knockout` - Full Knockout, stable single-elimination bracket.
- `group_knockout` - Group Stage + Knockout, groups of four, top two qualify.

Standalone League is no longer selectable. The migration maps old `knockout` rows to `full_knockout` and old `league` rows to `group_knockout`.

## Required Player Setup

New players are redirected to `profile_setup.html` after registration/login. They must select a system avatar, game, platform (`Mobile`, `PC`, or `Console`), and in-game identity before joining competitive tournaments. The onboarding page is a standalone shell; completed players use the normal Dashboard/Profile pages.

## Tournament Covers

Tournament covers are a server-managed library under `assets/tournament-covers`. The catalog synchronizes safe `.svg`, `.png`, `.jpg`, `.jpeg`, and `.webp` files into `tournament_cover_images` without reactivating admin-disabled rows. Missing files fall back to the default cover in player-facing UI and appear as missing in admin cover management.

## Automatic Starts

When an open tournament reaches capacity, KICKOFF schedules `auto_start_at` no later than `AUTO_START_HOURS_AFTER_FILL` hours after it became full. The default is 12 hours. Check-in opens before that time, and the lifecycle job starts due tournaments only when required participant payments are resolved.

## Payments

Live wallet behavior remains disabled. Paid tournament joins create pending payment records and reservations, then open ClickPesa checkout only when payment mode is enabled. Participation is confirmed only from backend webhook confirmation.

Set these before live payment testing:

- `PAYMENT_MODE=sandbox` or the confirmed live mode.
- `CLICKPESA_API_KEY`.
- ClickPesa application webhook URL: `APP_URL/api/payments/clickpesa_webhook.php`.
- Exact ClickPesa checksum/webhook verification settings from the ClickPesa dashboard.

## Cron / Windows Task Scheduler

Run these periodically. PHP does not create its own background scheduler in XAMPP, so configure Windows Task Scheduler or an equivalent service to execute them every few minutes:

```bat
C:\xampp\php\php.exe C:\xampp\htdocs\kickoff5\app\Jobs\ExpireTournamentReservations.php
C:\xampp\php\php.exe C:\xampp\htdocs\kickoff5\app\Jobs\TournamentLifecycleCron.php
```

Production cron example:

```cron
*/5 * * * * php /var/www/kickoff5/app/Jobs/ExpireTournamentReservations.php
*/5 * * * * php /var/www/kickoff5/app/Jobs/TournamentLifecycleCron.php
```
