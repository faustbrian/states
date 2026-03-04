[![GitHub Workflow Status][ico-tests]][link-tests]
[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Total Downloads][ico-downloads]][link-downloads]

------

A flexible state management package for Laravel that allows any model or object to have multiple states with full transition history, context isolation, and environment support.

## Features

- **Multiple States Per Object** - Assign unlimited states to any context
- **Namespaced States** - Organize states with optional namespaces (e.g., `workflow.pending`)
- **Context & Boundary Scoping** - Isolate states by context and optional boundary
- **Environment Support** - Separate states across environments (production, staging, etc.)
- **Full Transition History** - Track every state change with actor, reason, and metadata
- **State Machine Validation** - Configure allowed transitions per context type
- **Fluent API** - Chainable conductor-based operations
- **100% Test Coverage** - Comprehensive test suite with PHPStan max level

## Requirements

> **Requires [PHP 8.4+](https://php.net/releases/) and Laravel 11+**

## Installation

```bash
composer require cline/states
```

Publish the migration and run it:

```bash
php artisan vendor:publish --tag="states-migrations"
php artisan migrate
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="states-config"
```

## Quick Start

```php
use Cline\States\Facades\States;

// Assign a state
$user = User::find(1);
States::for($user)->assign('active');

// Transition between states
States::for($user)
    ->by($admin)
    ->because('User completed onboarding')
    ->transition('pending', 'active');

// Check states
States::hasState($user, 'active'); // true

// Get all states
$states = States::getStates($user);
```

## Documentation

- **[Getting Started](docs/README.md)** - Installation, configuration, and quick start
- **[Basic Usage](docs/basic-usage.md)** - Assigning, transitioning, and removing states
- **[Contexts and Boundaries](docs/contexts-and-boundaries.md)** - Scoping states to specific contexts
- **[Environments](docs/environments.md)** - Managing states across different environments
- **[Transition History](docs/transition-history.md)** - Tracking and querying state changes
- **[State Machines](docs/state-machines.md)** - Validating allowed transitions
- **[Advanced Usage](docs/advanced-usage.md)** - Events, metadata, and advanced patterns
- **[Configuration](docs/configuration.md)** - Advanced configuration options

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CODE_OF_CONDUCT](CODE_OF_CONDUCT.md) for details.

## Security

If you discover any security related issues, please use the [GitHub security reporting form][link-security] rather than the issue queue.

## Credits

- [Brian Faust][link-maintainer]
- [All Contributors][link-contributors]

## License

The MIT License. Please see [License File](LICENSE.md) for more information.

[ico-tests]: https://github.com/faustbrian/states/actions/workflows/quality-assurance.yaml/badge.svg
[ico-version]: https://img.shields.io/packagist/v/cline/states.svg
[ico-license]: https://img.shields.io/badge/License-MIT-green.svg
[ico-downloads]: https://img.shields.io/packagist/dt/cline/states.svg

[link-tests]: https://github.com/faustbrian/states/actions
[link-packagist]: https://packagist.org/packages/cline/states
[link-downloads]: https://packagist.org/packages/cline/states
[link-security]: https://github.com/faustbrian/states/security
[link-maintainer]: https://github.com/faustbrian
[link-contributors]: ../../contributors
