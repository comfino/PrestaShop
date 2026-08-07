# Comfino Payment Gateway for PrestaShop

[![PHP Version](https://img.shields.io/badge/php-5.6%20to%208.x-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-OSL--3.0-green.svg)](LICENSE)

> **Notice:** Version 3.6.0 is the **final release of the legacy line** for **PHP 5.6** and **PrestaShop 1.6**. It keeps older shops working by moving them onto the current Comfino Paywall and the frontend SDK, while retiring the deprecated paywall/widget scripts. **No further updates to this line are planned.**
>
> If your environment supports **PHP 7.1 or higher**, please upgrade to the **4.x** line. Shops running **PHP 8.1 or higher** should use **5.0.0 or higher**. We strongly recommend upgrading your store environment and moving off this legacy line as soon as possible.

PrestaShop payment module for Comfino deferred payments gateway - installment payments, buy now pay later (BNPL) and corporate payments.

## Installation

### Polish

[Installation guide (Polish)](docs/comfino.pl.md)

### English

[Installation guide (English)](docs/comfino.en.md)

## Compatibility

### This release (3.6.0 — final legacy release)

- **PrestaShop**: 1.6.x (minimal supported version is 1.6.0.14)
- **PHP**: 5.6 or higher
- **PHP extensions**: curl, json, zlib

### Newer environments

For modern environments use a newer line of the plugin instead of this legacy release:

- **[4.x](https://github.com/comfino/PrestaShop/releases)** — PrestaShop 1.6.1.11+, 1.7.x, 8.x, 9.x; PHP 7.1 or higher.
- **[5.0.0+](https://github.com/comfino/PrestaShop/releases)** — PrestaShop 1.7.7.0+, 8.x, 9.x; PHP 8.1 or higher (PrestaShop 1.6.x is not supported).

## Development

### Requirements

- PHP 5.6 or higher (production runtime)
- PrestaShop 1.6.0.14 or higher
- PHP extensions: curl, json, zlib
- Docker and Docker Compose (for local development)

Unit tests are run in Docker against PHP 5.6/7.1 to guarantee minimum-version compatibility, while code-style tooling runs on the host PHP.

### Local development setup

```bash
# Start development environment.
docker-compose up -d

# Install dependencies.
composer install

# Run tests.
./vendor/bin/phpunit
```

### Code style

```bash
# Fix code style (follows PrestaShop coding standards).
./vendor/bin/php-cs-fixer fix

# Check without fixing.
./vendor/bin/php-cs-fixer fix --dry-run
```

## License

This project is licensed under the Open Software License 3.0 - see the [LICENSE](LICENSE) file for details.

## Support

- Documentation (Polish): [Comfino PrestaShop plugin documentation](https://comfino.pl/plugins/PrestaShop/pl)
- Documentation (English): [Comfino PrestaShop plugin documentation](https://comfino.pl/plugins/PrestaShop/en)
- Issues: [GitHub Issues](https://github.com/comfino/PrestaShop/issues)
- Website: https://comfino.pl
