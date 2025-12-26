[![GitHub Workflow Status][ico-tests]][link-tests]
[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Total Downloads][ico-downloads]][link-downloads]

------

Cascade is a framework-agnostic resolver that fetches values from multiple sources in priority order, returning the first match. Perfect for implementing customer-specific → tenant-specific → platform-default fallback chains.

**Think:** "Get the FedEx credentials for this customer, falling back to platform defaults if they don't have their own."

## Requirements

> **Requires [PHP 8.4+](https://php.net/releases/)**

## Installation

```bash
composer require cline/cascade
```

## Documentation

Full documentation is available at **[docs.cline.sh/cascade](https://docs.cline.sh/cascade)**

- **[Getting Started](https://docs.cline.sh/cascade/getting-started)** - Installation and core concepts
- **[Basic Usage](https://docs.cline.sh/cascade/basic-usage)** - Simple examples and patterns
- **[Conductors](https://docs.cline.sh/cascade/conductors)** - Fluent API for building resolution chains
- **[Sources](https://docs.cline.sh/cascade/sources)** - Built-in and custom source types
- **[Named Resolvers](https://docs.cline.sh/cascade/named-resolvers)** - Managing multiple configurations
- **[Events](https://docs.cline.sh/cascade/events)** - Monitoring resolution lifecycle
- **[Cookbook](https://docs.cline.sh/cascade/cookbook)** - Real-world patterns and recipes

## Quick Example

```php
use Cline\Cascade\Cascade;
use Cline\Cascade\Source\CallbackSource;

// Build a resolution chain
$cascade = new Cascade();

$cascade->from(new CallbackSource(
    name: 'customer',
    resolver: fn($key, $ctx) => $customerDb->find($ctx['customer_id'], $key),
))
    ->fallbackTo(new CallbackSource(
        name: 'platform',
        resolver: fn($key) => $platformDb->find($key),
    ))
    ->as('credentials');

// Resolve with context
$apiKey = $cascade->using('credentials')
    ->for(['customer_id' => 'cust-123'])
    ->get('fedex-api-key');
// Tries customer source first, falls back to platform if not found
```

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

[ico-tests]: https://github.com/faustbrian/cascade/actions/workflows/quality-assurance.yaml/badge.svg
[ico-version]: https://img.shields.io/packagist/v/cline/cascade.svg
[ico-license]: https://img.shields.io/badge/License-MIT-green.svg
[ico-downloads]: https://img.shields.io/packagist/dt/cline/cascade.svg

[link-tests]: https://github.com/faustbrian/cascade/actions
[link-packagist]: https://packagist.org/packages/cline/cascade
[link-downloads]: https://packagist.org/packages/cline/cascade
[link-security]: https://github.com/faustbrian/cascade/security
[link-maintainer]: https://github.com/faustbrian
[link-contributors]: ../../contributors
