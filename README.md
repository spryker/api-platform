# ApiPlatform Module

[![Latest Stable Version](https://poser.pugx.org/spryker/api-platform/v/stable.svg)](https://packagist.org/packages/spryker/api-platform)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%208.3-8892BF.svg)](https://php.net/)

## Spryker API Platform Module

Use this module to integrate the [API Platform](https://api-platform.com/docs/symfony/) into a Spryker project.
A schema-based code generator for API Platform resources in Spryker applications. Define your API resources using YAML schemas and automatically generate PHP classes with full API Platform attribute support.

### Overview

This module provides a developer-friendly way to define API Platform resources through declarative schema files, similar to Spryker's Transfer object pattern. Resources can be defined across core, feature, and project layers with automatic merging and validation.

### Features

- 📝 **Schema-based Definition**: Define resources using YAML
- 🔄 **Multi-layer Support**: Core, Feature, and Project layer schemas
- 🎯 **ApiType Isolation**: Separate configurations for Storefront, Backend, etc.
- ✅ **Comprehensive Validation**: Post-merge validation with helpful errors
- 🚀 **Efficient Generation**: Generator-based file discovery for memory efficiency
- 💾 **Smart Caching**: Automatic cache invalidation based on file changes

## Installation

```
composer require spryker/api-platform
```

### Configuration

Default configuration (can be overridden in the bundle config: '%kernel.project_dir%/config/Symfony/{APPLICATION}/packages/spryker_api_platform.php'):

```yaml
spryker_api_platform:
  source_directories:
    - src/Spryker
    - src/Pyz
  cache_dir: '%kernel.cache_dir%/api-generator'
  generated_dir: '%kernel.project_dir%/src/Generated/Api'
  debug: '%kernel.debug%'
```

## Usage

```bash
# Generate all ApiTypes
docker/sdk glue api:generate

# Generate specific ApiType
docker/sdk glue api:generate Storefront

# Debug a resource
docker/sdk glue api:debug Customer --api-type=Storefront
```
