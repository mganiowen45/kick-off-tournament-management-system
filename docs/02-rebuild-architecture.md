# KICKOFF Rebuild Architecture

## Compatibility rules

- Keep existing page filenames and API endpoint URLs.
- Keep the existing visual system and responsive layouts.
- Keep existing table names, IDs, and relationships.
- Add only compatibility-safe columns/tables needed for missing features and security.
- Keep PHP 8+, PDO, MySQL, HTML, CSS, and vanilla JavaScript only.
- Keep wallet functionality disabled.

## Target folders

```text
app/
  Controllers/   HTTP orchestration only
  Core/          database, request, response, session, auth, CSRF
  Services/      tournament, match, result, chat, notification rules
  Support/       avatars and secure image handling
api/             thin compatibility entry points
assets/
  avatars/       built-in SVG avatar library
  css/
  js/
config/          environment-aware application configuration
database/
  migrations/    compatibility-safe incremental SQL
docs/            audit, architecture, setup, and verification reports
includes/        backwards-compatible page helpers
storage/         application logs/runtime data; not publicly served
uploads/results/ screenshot proof only
```

## Request flow

```text
Existing page/API URL
        |
        v
bootstrap + secure session
        |
        v
controller (method/auth/CSRF/input)
        |
        v
domain service (business transaction)
        |
        v
PDO repository/query + MySQL
        |
        v
consistent JSON response or existing view
```

## Planned minimal database migration

- Make `users.avatar_url` contain a safe built-in avatar filename, backfill nulls, and give it a default.
- Add `users.championships` for an inexpensive, explainable profile statistic.
- Add `tournaments.winner_id` and a random `share_token` for champions/private invites.
- Add `announcements` for tournament announcements.
- Add `achievements` and `user_achievements` for real badges.
- Add `login_attempts` for server-side login throttling.
- Add `group_message_reads` for per-user group unread state.
- Add optional `notifications.actor_id` so identity-bearing notifications can display the actor's avatar.
- Add supporting indexes and foreign keys without renaming or deleting current columns.

## Domain rules

### Authentication

- Passwords use `password_hash`/`password_verify`.
- Session IDs rotate at login and periodically.
- Account status is rechecked from the database.
- All writes require CSRF validation, including login/logout.
- Login throttling is keyed by IP plus normalized identifier in MySQL.
- Remember-me cookies contain selector/validator values; only a validator hash is stored.
- Password changes require the current password.

### Avatars

- The database stores only an approved filename.
- `AvatarCatalog` is the single allowlist and category source.
- Registration assigns a default avatar.
- Profile settings list all catalog entries and update immediately.
- APIs return both the identifier and resolved asset URL.
- Adding a future avatar requires only a new asset and catalog entry.

### Tournaments and matches

- Joining locks the tournament row before checking capacity.
- Leaving is allowed only before the tournament starts.
- Creators/admins may edit eligible tournaments and start them manually.
- 1v1 always has two players.
- Knockout rounds are generated one round at a time, including safe bye handling.
- League schedules use a round-robin circle algorithm.
- Completion records a champion and increments championships exactly once.

### Results and disputes

- A result must belong to the submitting player and its claim must match its score.
- The second submission verifies under a transaction and row lock.
- Matching submissions confirm once and update all statistics once.
- Conflicts create one dispute and notify players/admins.
- Admin decisions support player 1, player 2, draw, and replay.
- Resolution saves outcome, note, resolver, time, scores, stats, advancement, and notifications.

### Chat and notifications

- Messages are stored as plain validated text and escaped only when rendered.
- Polling uses `after_id` instead of repeatedly downloading the first page.
- Group membership is required for group reads/writes.
- Per-user group read pointers provide meaningful unread counts.
- Identity-bearing responses include selected avatars.

## Refactoring sequence and tests

1. Core/bootstrap/security classes; PHP syntax and focused unit checks.
2. Authentication/profile/avatar APIs and UI; registration/login/profile checks.
3. Tournament CRUD/membership/scheduling; transaction and generation checks.
4. Result/dispute/statistics/achievement pipeline; deterministic service tests.
5. Chat/notifications/admin APIs and UI; authorization tests.
6. Page integration, JavaScript compilation, link/asset scan, desktop/mobile browser checks.
7. MySQL migration/import/API checks when a MySQL service is available.

