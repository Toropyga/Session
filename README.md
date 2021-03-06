# Session
Библиотека для работы с сессиями в PHP

![License](https://img.shields.io/badge/license-MIT-brightgreen.svg)
![Version](https://img.shields.io/badge/version-v2.1.0-blue.svg)
![PHP](https://img.shields.io/badge/php-v5.5_--_v8-blueviolet.svg)


## Содержание

- [Общие понятия](#общие-понятия)
- [Установка](#Установка)
- [Настройка](#Настройка)
- [Подключение](#Подключение)
- [Пример использования](#пример-использования)


## Общие понятия

Класс Session - это библиотека для работы с сессиями в PHP.
Для работы необходимо наличие PHP версии 5 и выше.
Может сохранять данные сессии пользователя в базе данных.
Работает с БД MySQL и Postgre.
Автоматически создаёт таблицу в БД, если она не существует, для хранения данных сессии.


## Установка
Рекомендуемый способ установки библиотеки NetContent с использованием [Composer](http://getcomposer.org/):

```bash
composer require toropyga/session
```

## Настройка
Предварительная настройка параметров по умолчанию может осуществлятся или непосредственно в самом классе, или с помощью именованных констант.
Именованные константы при необходимости обявляются до вызова класса, например, в конфигурационном файле, и определяют параметры по умолчанию.
* SE_LIVETIME - время "жизни" простой (гостевой) сессии (сек.)
* SE_LIVETIME_REM - время "жизни" сохранённой сессии (сек.)
* SE_NAME - имя сессии
* SE_USEDB - использовать БД (true|false)
* SE_SECURE - параметр безопасности "secure" для COOKIE (true|false)
* SE_HTTPONLY - параметр безопасности "httponly" для COOKIE (true|false)
* SE_SAMESITE - порядок кроссдоменной передачи куки (lax|strict|none)
* SE_USE_TMPL - создать специальную директорию (папку) для хранения сессионных файлов (true|false)
* SE_TMPL_NAME - Имя директории для хранения сессии
* SE_USE_SERVER_NAME - использовать имя сервера при создании директории (true|false)
* SE_USE_SDIR - использовать стандартную папку для хранения сессий (true|false)
* SE_LOG_NAME - имя файла в который сохраняется лог
* SE_DEBUG - включить или выключить отладку и запись в лог


## Подключение
```php
require_once("vendor/autoload.php");
```
Инициализация класса
```php
$SE = new FYN\Session();
```


## Пример использования

```php
$SE = new FYN\Session();
$SE->setDebug(true); // включить отладку
$SE->sessionInit();
```
