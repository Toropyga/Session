# Changelog

All notable changes to this project are documented in this file.

## [Unreleased]

## [3.1.2] - 2026-10-22

### Added
- Added CSRF token support: a per-session token is generated automatically (via `random_bytes()`), stored in `$_SESSION['csrf_token']`, and persisted across requests. Retrieve it with the new `getCsrfToken(): string` method to embed in forms/AJAX requests for CSRF protection.

### Fixed
- Fixed the new CSRF token not being restored from an existing session: `getCsrfToken()` was returning an empty string on every request after the first one, because the token stored in `$_SESSION['csrf_token']` was never synced back to the in-memory property. The token is now loaded from `$_SESSION` when present, and only generated when missing.

### Verification
- `php -l src/Session.php` returned: `No syntax errors detected in src/Session.php`.
- Verified CSRF token and session data persistence across two simulated requests sharing the same session ID.

## [3.1.1] - 2026-09-11

### Security
- Session IDs are generated using `random_bytes()` instead of a time-based random fallback.
- Session IDs from cookies are accepted only if they match a strict hex format.
- Session data loaded from the database is deserialized with `allowed_classes => false` to reduce PHP object injection risk.
- Session storage directories now use restrictive `0750` permissions instead of the less secure `0777`.
- Duplicate `Set-Cookie` headers were removed to avoid sending the same session cookie twice.

### Improvements
- Added typed properties and return types.
- Added support for overriding the database type via the `SE_DB_TYPE` constant.
- Added a `SE_NO_CACHE_HEADERS` option to enable or disable no-cache headers.
- Added support for strict IP validation via `SE_STRICT_IP`.
- Improved session initialization and validation logic.

### Maintenance
- SQL generation and validation were tightened to avoid unsafe or malformed session IDs being used in database queries.
- The project documentation was updated to describe the new security-focused behavior.

---

### Verification
- `php -l src/Session.php` returned: `No syntax errors detected in src/Session.php`.
