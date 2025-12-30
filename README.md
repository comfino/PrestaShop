# Comfino Payment Gateway for PrestaShop

[![Tests](https://github.com/comfino/PrestaShop/workflows/Tests/badge.svg)](https://github.com/comfino/PrestaShop/actions)
[![PHP Version](https://img.shields.io/badge/php-7.1%20to%208.4-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-OSL--3.0-green.svg)](LICENSE)

PrestaShop payment module for Comfino deferred payments gateway - installment payments, buy now pay later (BNPL) and corporate payments.

## Installation

### Polish

[Installation guide (Polish)](https://github.com/comfino/PrestaShop/blob/master/docs/comfino.pl.md)

### English

[Installation guide (English)](https://github.com/comfino/PrestaShop/blob/master/docs/comfino.en.md)

## Compatibility

- **PrestaShop**: 1.6.x, 1.7.x, 8.x, 9.x (minimal supported version of PrestaShop is 1.6.1.11)
- **PHP**: 7.1 or higher
- **PHP extensions**: curl, json, zlib

For legacy environments the latest version of the plugin compatible with PHP 5.6 and PrestaShop 1.6.0.14+: [3.5.5](https://github.com/comfino/PrestaShop/releases/tag/3.5.5)   
It can be downloaded from here: [comfino.zip](https://github.com/comfino/PrestaShop/releases/download/3.5.5/comfino.zip)  
We strongly recommend upgrading your store environment to at least version 1.7.8.11 and using plugins version 4.x.

## Development

### Requirements

- PHP 7.1 or higher
- PrestaShop 1.6.1.11 or higher
- PHP extensions: curl, json, zlib
- Docker and Docker Compose (for local development)

### Local development setup

```bash
# Start development environment.
docker-compose up -d

# Install dependencies.
./bin/composer install

# Run tests.
./bin/composer test

# Run tests with coverage.
XDEBUG_MODE=coverage ./bin/phpunit --coverage-html coverage
```

### Running tests

```bash
# Using Composer.
./bin/composer test

# Direct PHPUnit execution.
./bin/phpunit

# With specific test file.
./bin/phpunit tests/MainTest.php

# With coverage report.
XDEBUG_MODE=coverage ./bin/phpunit --coverage-html coverage
```

### Code style

```bash
# Fix code style (follows PrestaShop coding standards).
./bin/csfixer

# Check without fixing.
./vendor/bin/php-cs-fixer fix --dry-run
```

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

All pull requests are automatically tested against PHP 7.1-8.4 with both lowest and stable dependencies.

## License

This project is licensed under the Open Software License 3.0 - see the [LICENSE](LICENSE) file for details.

## Support

- Documentation (Polish): [Comfino PrestaShop plugin documentation](https://comfino.pl/plugins/PrestaShop/pl)
- Documentation (English): [Comfino PrestaShop plugin documentation](https://comfino.pl/plugins/PrestaShop/en)
- Issues: [GitHub Issues](https://github.com/comfino/PrestaShop/issues)
- Website: https://comfino.pl
