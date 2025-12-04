# API Platform Configuration

## Configuring API Types for Cache Warming

The `ApiResourceCacheWarmer` can now be configured from your project using Symfony's configuration system.

### Location

Create or edit: `/config/Symfony/{APPLICATION}/packages/spryker_api_platform.php`

### Example Configuration

```php
<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('spryker_api_platform', [
        'api_types' => [
            'backoffice',
            'storefront',
            'merchant-portal',
        ],
    ]);
};
```

### Available Options

- **`api_types`** (array): List of API types to generate and cache warm
  - Common values: `'backoffice'`, `'storefront'`, `'merchant-portal'`
  - Leave empty `[]` to generate all found API types
  - Default: `[]`

- **`source_directories`** (array): Directories to search for API schema files
  - Default: `['src/Spryker', 'src/SprykerFeature', 'src/Pyz']`

- **`generated_dir`** (string): Directory where generated resources are written
  - Default: `'src/Generated/Api'`

- **`cache_dir`** (string): Cache directory for generated resources
  - Default: `'%kernel.cache_dir%/api-generator'`

- **`debug`** (bool): Enable debug mode (disables caching, enables verbose output)
  - Default: `%kernel.debug%`

### How It Works

1. The configuration is loaded by `SprykerApiPlatformExtension`
2. Parameters are registered in the DI container with prefix `spryker_api_platform.*`
3. The `ApiResourceCacheWarmer` receives the `api_types` array via constructor injection
4. During cache warmup, only the configured API types are generated

### Testing the Configuration

After adding the configuration file, clear and warm the cache:

```bash
# Clear cache
vendor/bin/console cache:clear

# Warm cache (this will trigger ApiResourceCacheWarmer)
vendor/bin/console cache:warmup
```

