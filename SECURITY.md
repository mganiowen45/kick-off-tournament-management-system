# Security Notes

Implemented controls:

- Environment-based configuration through `.env`.
- Production debug flag support.
- HttpOnly session cookies with configurable secure and SameSite settings.
- Session rotation and idle timeout.
- CSRF tokens on app-layer state-changing endpoints.
- A client fetch shim that obtains the current-session token from `api/users/session.php` and sends `X-CSRF-Token` for same-origin unsafe requests.
- Incomplete player profiles are blocked from competitive pages and API actions with `PROFILE_SETUP_REQUIRED`.
- Prepared PDO statements in the app service layer.
- Content security, frame, referrer, and content-type headers.
- WhatsApp numbers are never returned in tournament or public profile payloads.
- WhatsApp contact is authorized per match participant and audited.
- Contact abuse reports and user block tables are available.
- Private tournament detail, bracket, standings, and fixture data are routed through service-level visibility checks.
- Paid joins reserve slots but only backend-verified payment events can confirm financial participation.
- ClickPesa live webhooks fail closed until the exact dashboard checksum method is configured.

Production checklist:

- Do not use `root` as `DB_USER`.
- Do not deploy with a blank `DB_PASSWORD`.
- Set `APP_DEBUG=false`.
- Set `SESSION_SECURE_COOKIE=true` behind HTTPS.
- Keep `.env`, logs, uploads, and evidence out of version control.
- Store future evidence files outside public web roots and serve through an authorization controller.