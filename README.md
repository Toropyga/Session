# Session
A library for working with sessions in PHP

![License](https://img.shields.io/badge/license-MIT-brightgreen.svg)
![Version](https://img.shields.io/badge/version-v3.1.0-blue.svg)
![PHP](https://img.shields.io/badge/php-v7_--_v8-blueviolet.svg)


## Table of Contents

- [Overview](#overview)
- [Installation](#installation)
- [Configuration](#configuration)
- [Public Methods](#public-methods)
- [Getting started](#getting-started)
- [Usage example](#usage-example)


## Overview

The Session class is a library for working with sessions in PHP.
It requires PHP version 7.0 or higher (session IDs are generated with `random_bytes()`, and the library uses typed properties and `declare(strict_types=1)`).
It can store user session data in a database.
It works with MySQL and PostgreSQL databases.
It automatically creates a table in the database, if one does not already exist, to store session data.


## Installation
The recommended way to install the Session library is using [Composer](http://getcomposer.org/):

```bash
composer require toropyga/session
```

## Configuration
Default parameters can be pre-configured either directly in the class itself, or by using named constants.
Named constants, if needed, should be defined before the class is called — for example, in a configuration file — and they determine the default settings.
* SE_LIVETIME - lifetime of a simple (guest) session, in seconds
* SE_LIVETIME_REM - lifetime of a saved (remembered) session, in seconds
* SE_NAME - session name
* SE_USEDB - whether to use a database (true|false)
* SE_DB_TYPE - database type to use for storing sessions (mysql|postgre); postgre support is experimental
* SE_SECURE - the "secure" security parameter for the COOKIE (true|false)
* SE_HTTPONLY - the "httponly" security parameter for the COOKIE (true|false)
* SE_SAMESITE - cross-domain cookie transfer policy (lax|strict|none)
* SE_USE_TMPL - create a dedicated directory (folder) for storing session files (true|false)
* SE_TMPL_NAME - name of the directory used to store sessions
* SE_USE_SERVER_NAME - use the server name when creating the directory (true|false)
* SE_USE_SDIR - use the standard folder for storing sessions (true|false)
* SE_LOG_NAME - name of the file the log is saved to
* SE_DEBUG - enable or disable debugging and logging
* SE_STRICT_IP - invalidate the session data if the client's IP address changes mid-session (true|false, default: false). Disabled by default because legitimate users on mobile networks, corporate proxies, or CDNs can change IP address between requests.
* SE_NO_CACHE_HEADERS - whether the library should send `Cache-Control` / `Expires` / `Pragma` "no-cache" headers on every request that starts a session (true|false, default: true). Disable this if your application manages its own caching headers.

> **Security note (v3.1.0):** session identifiers are now generated using `random_bytes()` (a CSPRNG) instead of a hash of the current time, and any session ID coming from the client's cookie is only accepted if it matches a strict hexadecimal format; otherwise a new one is generated. Session data loaded from the database is unserialized with `allowed_classes => false` to prevent PHP object injection, and session-file directories are created with `0750` permissions instead of `0777`.


## Public Methods

### `sessionInit(): void`
Initializes the session: connects to the database (if `SE_USEDB` is enabled) and creates the sessions table if it doesn't exist yet, then starts the PHP session and, when a database is used, loads the stored session data into `$_SESSION`. This is normally the first method you call after instantiating the class.

### `setSession(): bool`
Persists the current `$_SESSION` data to the database (insert or update, depending on whether a row for the current session ID already exists). Called automatically from the destructor, so you rarely need to call it manually — but it's available if you want to force an immediate save (e.g. right after changing `$_SESSION['user_id']` on login, before doing a redirect). Returns `false` if the session hasn't been initialized yet or the session ID is missing/invalid.

### `setDebug(bool $debug = false): void`
Enables or disables debug logging. When enabled, internal steps (session path, session ID, data loaded/saved, etc.) are recorded and can be retrieved with `getLogs()`.

### `getLogs(): array`
Returns the collected debug logs as an array with two keys:
* `log` - the array of log messages
* `file` - the log file name (`SE_LOG_NAME`) they are intended to be written to

### `setMyCookie(string $name = '', string $value = '', int $live_time = 0, string $domain = '', ?bool $secure = null, ?bool $http_only = null, ?string $samesite = null): bool`
Sets a cookie using the same security defaults (`secure`, `httponly`, `samesite`) configured for the session. Under normal operation the session cookie is already sent for you by `sessionInit()`/`getSession()`, so you don't need to call this yourself for the session cookie itself. It's provided as a public utility for cases where you need to issue an additional cookie manually — for example, re-issuing a "remember me" cookie with a custom lifetime or domain.

Parameters:
* `$name` - name of the cookie to set
* `$value` - value to store in the cookie. If omitted (empty string), the current session ID (`$this->sid`) is used instead
* `$live_time` - cookie lifetime in seconds, counted from now. If omitted (`0`), the default guest session lifetime (`SE_LIVETIME` / `$session_live_time`) is used
* `$domain` - domain the cookie is issued for. If omitted, it's derived automatically from `$_SERVER['SERVER_NAME']`
* `$secure` - overrides the "secure" cookie flag (cookie only sent over HTTPS). If `null`, the class-wide default (`SE_SECURE`) is used
* `$http_only` - overrides the "httponly" cookie flag (cookie not accessible via JavaScript). If `null`, the class-wide default (`SE_HTTPONLY`) is used
* `$samesite` - overrides the SameSite policy (`lax`|`strict`|`none`). If `null`, the class-wide default (`SE_SAMESITE`) is used

Returns `true` on success, `false` if the cookie could not be set (same as PHP's native `setcookie()`).

### `__construct()`
Reads configuration from the named constants (see [Configuration](#configuration)) and prepares the session storage (database table definitions, session file directory) without starting the session yet.

### `__destruct()`
Automatically calls `setSession()` to persist session data to the database when the object is destroyed at the end of the script.


## Getting started
```php
require_once("vendor/autoload.php");
```
Initialize the class
```php
$SE = new Toropyga\Session();
```


## Usage example

```php
$SE = new Toropyga\Session();
$SE->setDebug(true); // enable debugging
$SE->sessionInit();
```
