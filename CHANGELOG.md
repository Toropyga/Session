# Changelog

All notable changes to this project are documented in this file.

## [Unreleased]

### Added
- Added a project changelog file to document releases and notable changes.

### Reviewed
- Verified the main implementation in [src/Session.php](src/Session.php) and confirmed it parses successfully.
- Checked the project metadata in [composer.json](composer.json) and the docs in [README.md](README.md).

### Notes
- The project is now aligned to version 3.1.1 across the class header and documentation.
- The implementation requires PHP 7.4+ and the dependency constraints were aligned to reflect that support.

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
