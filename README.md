# phpdot/package

Attribute-driven package scanning, definition generation, and config scaffolding for PHPdot.

Scans vendor packages for container attributes (`#[Singleton]`, `#[Scoped]`, `#[Config]`, `#[Binds]`), generates cached PHP definitions, and scaffolds config files with defaults and override hints.

---

## Install

```bash
composer require phpdot/package
```

Add the Composer script to your project:

```json
{
    "scripts": {
        "post-autoload-dump": [
            "PHPdot\\Package\\Composer\\ComposerScript::postAutoloadDump"
        ]
    }
}
```

Every `composer install/update/require/remove` triggers a scan automatically.

---

## How It Works

```
composer require phpdot/i18n
    ↓
PackageScanner reads vendor/composer/installed.json
    → finds packages with phpdot/container in require or require-dev
    → reflects attributed classes (#[Singleton], #[Scoped], #[Config], #[Binds])
    ↓
DefinitionGenerator → vendor/phpdot/definitions.php (cached container definitions)
ManifestGenerator   → vendor/phpdot/manifest.php (package metadata)
ConfigFileGenerator → config/{name}.php (once, never overwritten)
```

At boot time:

```php
PackageBootstrap::load($vendorPath, $builder);
// → requires vendor/phpdot/definitions.php
// → $builder->addDefinitions($definitions)
```

Zero reflection at runtime. One `require`. OPcached.

---

## Directory Convention

```
my-app/
├── config/              VALUES — developer edits these
│   ├── i18n.php         auto-generated from #[Config('i18n')]
│   └── cache.php        auto-generated from #[Config('cache')]
│
├── container/           WIRING — optional, power users
│   ├── services.php     definition overrides (last wins)
│   └── bindings.php     contextual bindings
│
└── vendor/
    └── phpdot/          auto-generated, never edited
        ├── definitions.php
        └── manifest.php
```

`config/` for values. `container/` for DI overrides. `vendor/phpdot/` for framework internals.

---

## Generated Config Files

When a package has a `#[Config]` DTO, the scanner generates a config file with defaults and hints:

```php
<?php

/**
 * phpdot/i18n
 *
 * Services:
 *   Translator           scoped     (LoaderInterface, CacheInterface, I18nConfig)
 *   ICUValidator          singleton
 *   PhpArrayLoader        singleton  → binds LoaderInterface
 *
 * Override in container/services.php:
 *   LoaderInterface::class => singleton(fn ($c) => new YourImpl(...))
 *
 * Contextual binding in container/bindings.php:
 *   $builder->when(Consumer::class)->needs(CacheInterface::class)->provide(YourImpl::class);
 */

return [
    'default' => 'en',
    'supported' => ['en'],
    'path' => '',
    'ttl' => 3600,
];
```

The developer sees every service, its scope, dependencies, and copy-paste override examples.

---

## PackageManager

Central API for scanning, generating, and clearing.

```php
use PHPdot\Package\PackageManager;

$manager = new PackageManager($vendorPath, $configPath);

$result = $manager->rebuild();
// $result->packageCount, serviceCount, bindingCount, configCount, generatedConfigs

$manager->clear();
// deletes vendor/phpdot/definitions.php and manifest.php
// does NOT delete config files

$manifest = $manager->manifest();
// $manifest->packageNames()
// $manifest->allServices()
// $manifest->allBindings()
// $manifest->allConfigs()
```

---

## PackageBootstrap

Boot-time loader. One static method.

```php
use PHPdot\Package\PackageBootstrap;

$builder = new ContainerBuilder();
PackageBootstrap::load($vendorPath, $builder);
// loads vendor/phpdot/definitions.php into builder
```

---

## Configurable Paths

Override config directory via `composer.json` extra:

```json
{
    "extra": {
        "phpdot": {
            "config-dir": "config"
        }
    }
}
```

---

## Attributes Reference

| Attribute | Target | Effect |
|-----------|--------|--------|
| `#[Singleton]` | class | Registered as singleton in container |
| `#[Scoped]` | class | Fresh instance per request/coroutine |
| `#[Transient]` | class | New instance every resolution |
| `#[Config('name')]` | class | Singleton, hydrated from config/{name}.php |
| `#[Binds(Interface::class)]` | class | Registers as default for interface (repeatable) |

---

## API Reference

### PackageManager

```
__construct(string $vendorPath, string $configPath)
rebuild(): RebuildResult
clear(): void
manifest(): ?Manifest
definitionsPath(): string
manifestPath(): string
```

### PackageBootstrap

```
static load(string $vendorPath, ContainerBuilder $builder): void
```

### PackageScanner

```
scan(string $vendorPath): list<ScannedClass>
scanDirectory(string $directory, string $namespace, string $package): list<ScannedClass>
```

### Manifest

```
packageNames(): list<string>
allServices(): array<string, string>
allBindings(): array<string, string>
allConfigs(): array<string, string>
```

### RebuildResult

```
int $packageCount
int $serviceCount
int $bindingCount
int $configCount
list<string> $generatedConfigs
```

---

## License

MIT
