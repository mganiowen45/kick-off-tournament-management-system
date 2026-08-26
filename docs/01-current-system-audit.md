# KICKOFF Current-System Audit

Audit date: 2026-06-28

## Scope reviewed

- All 20 HTML/PHP-routed pages.
- All 54 PHP files and 39 API endpoints.
- `kickoff.sql`, reset/patch SQL, tables, indexes, foreign keys, views, and sample data.
- Shared navigation, page JavaScript, CSS, upload rules, Apache rules, sessions, authentication, authorization, and database access.
- Baseline PHP syntax and inline JavaScript syntax.
- The public interface in a browser at desktop size.

## Current application identity

KICKOFF is a dark, mobile-responsive football/esports tournament platform. Its visual identity uses a near-black grid background, lime primary accents, cyan secondary accents, orange/red warning accents, condensed gaming fonts, card-based content, a desktop sidebar/topbar, and a mobile bottom navigation.

The current player journey is:

1. Visit the public landing page.
2. Register or log in.
3. Browse, create, or join a tournament.
4. View a tournament's matches, participants, rules, chat, bracket, or league table.
5. Submit a score and screenshot proof.
6. Wait for the opponent's matching submission or an administrator's dispute decision.
7. View statistics, rankings, messages, and tournament history.

The administrator journey is:

1. Log in with an administrator account.
2. View platform statistics.
3. Moderate users.
4. Cancel tournaments.
5. Review conflicting result submissions.

The rebuild must preserve this identity, the existing `.html` page names, the current color/layout system, and the existing API URLs where they are already used.

## Existing database

The dump contains eight main tables:

- `users`
- `tournaments`
- `tournament_players`
- `matches`
- `match_results`
- `disputes`
- `messages`
- `notifications`

It also contains leaderboard, league-standing, active-tournament, dispute, and unread-message views. Primary keys, useful foreign keys, and several relevant indexes already exist.

The schema is a sound prototype base and will be migrated rather than replaced. Existing IDs and relationships will be retained.

## Feature status before rebuild

### Implemented and substantially working

- Registration and password hashing.
- Login/logout and role values.
- Public tournament browsing, search by name, and format/status filters.
- Tournament creation and joining.
- 1v1, knockout, and league values in the schema/UI.
- Tournament detail, participants, rules, match list, bracket view, and league standings view.
- Direct and tournament group messages.
- Screenshot result submission.
- Two-player result comparison and dispute creation.
- Leaderboard and basic player statistics.
- User warnings/bans, tournament cancellation, and dispute review screens.
- Responsive gaming-themed UI.
- Basic image MIME/extension validation and upload-directory script blocking.

### Partial, inconsistent, or broken

- `tournament_detail.html`, `admin_users.html`, and `admin_disputes.html` contain JavaScript syntax errors.
- Active tournament cards link to missing `tournament_ongoing.html`.
- Bracket code uses a missing `back-link` element.
- Group chat code uses a missing `nav-btns` element.
- Landing-page logged-in navigation references old/missing navigation IDs.
- Knockout advancement references a nonexistent `matches.match_number` column and assumes future matches already exist.
- Odd-size knockout fields can silently drop one player.
- League matches are assigned one match per “round” instead of a proper round-robin schedule.
- 1v1 allows capacities above two, but match generation only runs when exactly two players exist.
- Result verification exists in two different implementations with different behavior.
- Global points are awarded as `3/1/0` in one path and `100/40/10` in another.
- Confirmed results do not consistently update league stats, knockout elimination, notifications, or later rounds.
- The dispute UI offers a draw decision, but the backend rejects it.
- Dispute resolution does not save the outcome, admin note, resolver, or resolution time and does not update statistics consistently.
- Tournament creator cancellation uses a strict string/integer comparison and can deny the creator.
- The private-tournament visibility value exists, but listing and access control do not make tournaments private.
- Group unread counts use one global `is_read` field and cannot represent per-member read state.
- Chat polling repeatedly loads the first page, so newer messages can disappear after enough messages exist.
- Profile settings display an email field that the profile API does not return.
- Password changes do not require the current password.
- Remember-me support alters the schema during a web request and scans every unexpired token.
- Notifications have an API but no complete user-facing page.
- Tournament edit, leave, manual start, schedule management, and announcements are absent.
- Achievements/badges and championship counts are visual placeholders, not a database-backed feature.
- Avatars are initials; the requested built-in avatar library does not exist.
- Several requested tournament sections are combined or missing rather than being complete tabs/pages.
- Remote fonts/icons are required, so parts of the UI are not offline-safe.
- Multiple files contain mojibake/corrupted punctuation and emoji text.

### Excluded as requested

- Wallet pages and endpoints are already disabled stubs. They will remain excluded from navigation and application logic.

## Architecture and code-quality findings

- Most endpoints mix HTTP handling, validation, SQL, authorization, business rules, and response formatting in one file.
- Apart from the PDO singleton, the backend is procedural rather than object oriented.
- Similar queries and result rules are duplicated.
- Runtime schema inspection/alteration is performed from application requests.
- UI pages contain large inline scripts and construct substantial HTML with unescaped database values.
- Shared PHP components are mostly placeholders while JavaScript injects the real application shell.
- Error handling is inconsistent; some endpoints expose exception details when debug mode is enabled.
- There is no automated test suite or setup/readme documentation.

## Security findings

- CSRF helpers exist but no state-changing endpoint enforces a CSRF token.
- SameSite cookies alone are not complete CSRF protection.
- Login throttling is stored in the attacker's session and can be bypassed by clearing cookies.
- The remember-me token design is inefficient and schema changes occur at runtime.
- Account bans do not reliably invalidate an already-authenticated session.
- Result-proof data is visible to any logged-in user who guesses a match ID.
- Several administrator/player API responses are inserted into `innerHTML` without escaping.
- Input is HTML-encoded before storage in several places instead of being validated on input and escaped on output.
- CORS configuration is unnecessary for a same-origin application and includes an invalid path-based origin.
- Debug mode is enabled in the committed configuration.
- Database root/no-password values and a fixed site URL are committed.
- Logout and other write operations do not consistently enforce HTTP method or CSRF rules.
- Upload validation is a good start, but error text disagrees with configured limits and saved images are not normalized.

## Performance and data-integrity findings

- Session polling executes several count queries every 30 seconds on every page.
- Tournament counts are stored and incremented separately from membership rows without row locking.
- Join operations check capacity before the transaction, allowing concurrent overbooking.
- Result verification is not fully protected against concurrent second submissions.
- Remember-me authentication scans all token-bearing users.
- Group unread counts use correlated queries and an unsuitable data model.
- Some APIs return `SELECT *` when only a few fields are required.
- MySQL view definitions include a `root@localhost` definer, which reduces portability.
- The configured database name (`kickoff2`) does not match the dump comment (`kick_off`).

## Baseline verification

- PHP 8.5.7 is available.
- Every existing `.php` file passes `php -l`.
- Inline JavaScript compilation found three syntax-failing pages listed above.
- No MySQL/MariaDB service or client is currently available in this environment, so live database tests require a local MySQL installation or XAMPP/Laragon setup.
- The public landing page rendered successfully and confirms the design identity described above.

