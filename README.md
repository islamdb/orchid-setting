## Introduction

Orchid Setting

## Installation

> The manual assumes that you already have a copy of [Laravel](https://laravel.com/docs/installation) with [Orchid](https://orchid.software/en/docs/installation/)

You can install the package using the Сomposer. Run this at the command line:

```php
composer require islamdb/orchid-setting
```

This will update `composer.json` and install the package into the `vendor/` directory. And then you have to publish config and migration file. You can publish them by running this at the command line:

```php
php artisan vendor:publish --provider="IslamDB\OrchidSetting\OrchidSettingServiceProvider"
```
