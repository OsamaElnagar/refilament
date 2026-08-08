<div align="center">
    <h1>Refilament</h1>
</div>

<p align="center">
    <a href="https://packagist.org/packages/osamaelnagar/refilament"><img src="https://img.shields.io/packagist/v/osamaelnagar/refilament.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/osamaelnagar/refilament"><img src="https://img.shields.io/packagist/php-v/osamaelnagar/refilament.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://packagist.org/packages/osamaelnagar/refilament"><img src="https://badge.laravel.cloud/badge/osamaelnagar/refilament?style=flat" alt="Laravel versions"></a>
    <a href="https://github.com/osamaelnagar/refilament/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/osamaelnagar/refilament/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/osamaelnagar/refilament"><img src="https://img.shields.io/packagist/dt/osamaelnagar/refilament.svg?style=flat-square" alt="Total Downloads"></a>
</p>



## Installation

You can install the package via Composer:

```bash
composer require osamaelnagar/refilament
```

You may publish all of the package's resources at once:

```bash
php artisan vendor:publish --tag="refilament"
```

Or, you may publish each resource individually:

### Publishing the Configuration File

```bash
php artisan vendor:publish --tag="refilament-config"
```

### Publishing and Running the Migrations

```bash
php artisan vendor:publish --tag="refilament-migrations"
php artisan migrate
```

### Publishing the Views

```bash
php artisan vendor:publish --tag="refilament-views"
```

### Publishing the Translations

```bash
php artisan vendor:publish --tag="refilament-lang"
```

### Publishing the Public Assets

```bash
php artisan vendor:publish --tag="refilament-assets"
```

## Usage

<!-- Add a basic usage example here. -->

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Thank you for considering contributing to Refilament! Please review our [contributing guide](.github/CONTRIBUTING.md) to get started.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Osama Mohammed Elnagar](https://github.com/osamaelnagar)
- [All Contributors](../../contributors)

## License

Refilament is open-sourced software licensed under the [MIT license](LICENSE.md).
