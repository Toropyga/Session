# Session
A library for working with sessions in PHP

![License](https://img.shields.io/badge/license-MIT-brightgreen.svg)
![Version](https://img.shields.io/badge/version-v2.2.1-blue.svg)
![PHP](https://img.shields.io/badge/php-v5.5_--_v8-blueviolet.svg)


## Table of Contents

- [Overview](#overview)
- [Installation](#installation)
- [Configuration](#configuration)
- [Getting started](#getting-started)
- [Usage example](#usage-example)


## Overview

The Session class is a library for working with sessions in PHP.
It requires PHP version 5 or higher.
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
* SE_SECURE - the "secure" security parameter for the COOKIE (true|false)
* SE_HTTPONLY - the "httponly" security parameter for the COOKIE (true|false)
* SE_SAMESITE - cross-domain cookie transfer policy (lax|strict|none)
* SE_USE_TMPL - create a dedicated directory (folder) for storing session files (true|false)
* SE_TMPL_NAME - name of the directory used to store sessions
* SE_USE_SERVER_NAME - use the server name when creating the directory (true|false)
* SE_USE_SDIR - use the standard folder for storing sessions (true|false)
* SE_LOG_NAME - name of the file the log is saved to
* SE_DEBUG - enable or disable debugging and logging


## Getting started
```php
require_once("vendor/autoload.php");
```
Initialize the class
```php
$SE = new FYN\Session();
```


## Usage example

```php
$SE = new FYN\Session();
$SE->setDebug(true); // enable debugging
$SE->sessionInit();
```
