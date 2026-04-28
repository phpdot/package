# phpdot/package

Attribute-driven package scanning, definition generation, and config scaffolding for PHPdot.

Scans vendor packages for container attributes (`#[Singleton]`, `#[Scoped]`, `#[Transient]`, `#[Config]`, `#[Binds]`), generates a cached container definitions file, scaffolds config files with PHPDoc descriptions and environment overrides, and ships a CLI inspector (`vendor/bin/package`) for full visibility into what's installed and what can be overridden.

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
    → extracts package metadata (description, url, author)
    → reflects attributed classes (#[Singleton], #[Scoped], #[Transient], #[Config], #[Binds])
    → parses @param PHPDoc descriptions for #[Config] DTOs
    ↓
DefinitionGenerator      → vendor/phpdot/definitions.php    (cached container definitions)
ManifestGenerator        → vendor/phpdot/manifest.php       (package metadata + ownedConfigs ledger)
ConfigFileGenerator      → config/{name}.php                (once, never overwritten)
```

At boot time:

```php
$manager = new PackageManager(__DIR__);
$manager->load($builder);
// → requires vendor/phpdot/definitions.php
// → $builder->addDefinitions($definitions)
```

Zero reflection at runtime. One `require`. OPcached.

---

## Directory Convention

```
my-app/
├── config/                   VALUES — developer edits these
│   └── i18n.php              auto-generated from #[Config('i18n')]
│
└── vendor/
    └── phpdot/               auto-generated, never edited
        ├── definitions.php
        └── manifest.php
```

`config/` is for values. `vendor/phpdot/` is for framework internals.

Override default bindings directly in your application bootstrap using `phpdot/container`'s API — `add()`, `addDefinitions()`, or `when()->needs()->provide()`. No scaffolded override files.

---

## Configurable Paths

Override directories via `composer.json`:

```json
{
    "config": {
        "vendor-dir": "vendor"
    },
    "extra": {
        "phpdot": {
            "config-dir": "settings",
            "exclude": ["some/package"]
        }
    }
}
```

`PackageManager` reads `composer.json` from the base path and resolves all directories automatically. Defaults: `vendor-dir` = `vendor`, `config-dir` = `config`. Falls back to defaults if `composer.json` is missing. The `exclude` list skips those packages from scanning.

---

## Generated Config Files

When a package has a `#[Config]` DTO, the scanner generates a config file with PHPDoc descriptions from `@param` tags, default values, and environment override blocks:

```php
<?php

declare(strict_types=1);

/**
 * phpdot/i18n
 * Internationalization with ICU MessageFormat, pluggable loaders, PSR-16 caching.
 *
 * @package     phpdot/i18n
 * @see         https://github.com/phpdot/i18n
 * @see         phpdot/config
 * @generated   phpdot/package
 *
 * This is your file — modify it freely, we won't touch it.
 *
 * Note: `composer remove phpdot/i18n` does NOT delete this file.
 * phpdot/package will list it as orphaned on the next rebuild —
 * delete it manually to clean up.
 */

return [
    /**
     * Default language code
     */
    'default' => 'en',
    /**
     * Supported language codes
     */
    'supported' => ['en'],
    /**
     * Base path to translation files
     */
    'path' => '',
    /**
     * Cache TTL in seconds
     */
    'ttl' => 3600,

    /**
     * Environment overrides
     *
     * Values are merged on top of defaults based on the active environment.
     * Handled automatically by phpdot/config.
     */
    'development' => [
    ],
    'production' => [
    ],
    'staging' => [
    ],
];
```

Descriptions come from `@param` PHPDoc on the config DTO constructor. If no `@param` exists, the parameter name is humanized as fallback.

Nested DTOs (a `#[Config]` constructor parameter typed as another DTO class) are recursively scaffolded as nested arrays with the same convention.

---

## Generated Definitions

The `vendor/phpdot/definitions.php` file contains cached `ScopedDefinition` entries with professional docblock headers per package:

```php
<?php

/**
 * PHPdot Container Definitions
 *
 * @generated   phpdot/package
 * @date        2026-04-28T15:30:00+00:00
 * @see         https://github.com/phpdot/package
 *
 * Regenerated on every composer install/update/require/remove.
 * Do not edit — changes will be overwritten.
 */

declare(strict_types=1);

use PHPdot\Container\Definition\ScopedDefinition;
use PHPdot\Container\Scope;
use Psr\Container\ContainerInterface;

return [

    /**
     * phpdot/i18n
     * Internationalization with ICU MessageFormat, pluggable loaders, PSR-16 caching.
     *
     * @see https://github.com/phpdot/i18n
     */

    \PHPdot\I18n\I18nConfig::class => new ScopedDefinition(
        scope: Scope::SINGLETON,
        factory: static fn (ContainerInterface $c): \PHPdot\I18n\I18nConfig
            => $c->get(\PHPdot\Config\Configuration::class)->dto('i18n', \PHPdot\I18n\I18nConfig::class),
    ),

    // ...
];
```

---

## Generated Manifest

The `vendor/phpdot/manifest.php` file contains hand-formatted package metadata plus an `ownedConfigs` ledger used to detect orphaned config files when packages are removed:

```php
<?php

declare(strict_types=1);

/**
 * PHPdot Package Manifest
 *
 * @generated   phpdot/package
 * @date        2026-04-28T15:30:00+00:00
 * @see         https://github.com/phpdot/package
 *
 * Regenerated on every composer install/update/require/remove.
 * Do not edit — changes will be overwritten.
 */

return [

    'generated_at' => '2026-04-28T15:30:00+00:00',

    'ownedConfigs' => [
        '/app/config/i18n.php',
    ],

    'packages' => [

        'phpdot/i18n' => [
            'description' => 'Internationalization with ICU MessageFormat...',
            'url' => 'https://github.com/phpdot/i18n',
            'author' => 'Omar Hamdan <omar@phpdot.com>',
            'services' => [
                'PHPdot\\I18n\\I18nConfig' => 'SINGLETON',
                'PHPdot\\I18n\\Translator' => 'SCOPED',
            ],
            'configs' => [
                'PHPdot\\I18n\\I18nConfig' => 'i18n',
            ],
            'bindings' => [
                'PHPdot\\I18n\\Loader\\LoaderInterface' => 'PHPdot\\I18n\\Loader\\PhpArrayLoader',
            ],
        ],

    ],

];
```

`ownedConfigs` is consumed on the next rebuild: any path that was owned previously but isn't owned now is reported as orphaned. The framework never deletes user-owned files automatically; the developer is shown a warning and decides whether to delete.

---

## Overriding Defaults

Defaults from `definitions.php` can be overridden in your application bootstrap using the `phpdot/container` builder API. No scaffolded file is needed.

| Scenario | API |
|---|---|
| Replace globally with a single binding | `$builder->add($iface, $impl)->singleton()` |
| Replace globally with multiple bindings at once | `$builder->addDefinitions([$iface => $impl, ...])` |
| Replace only for one specific consumer | `$builder->when($consumer)->needs($iface)->provide($impl)` |

Example:

```php
use PHPdot\Container\ContainerBuilder;
use Psr\Http\Message\ResponseFactoryInterface;

$builder = new ContainerBuilder();

// Default: ResponseFactoryInterface → PHPdot\Http\ResponseFactory
// Override globally:
$builder->add(ResponseFactoryInterface::class, fn ($c) => new MyFactory())
    ->singleton();

// Override only when AdminController asks for it:
$builder->when(AdminController::class)
    ->needs(ResponseFactoryInterface::class)
    ->provide(AdminFactory::class);
```

To discover what's available to override, use the CLI inspector below.

---

## CLI Inspector

`phpdot/package` ships a binary at `vendor/bin/package` (Composer's `bin` mechanism — installs automatically). It provides full visibility into installed packages, services, configs, and bindings by reading `vendor/phpdot/manifest.php`.

```bash
vendor/bin/package list                  # all installed phpdot packages
vendor/bin/package show phpdot/http      # one package's full surface + override hints
vendor/bin/package paths                 # resolved paths (root, vendor, config, manifest)
vendor/bin/package config:list           # every config file, owner, presence
vendor/bin/package services              # every service across all packages, scope, owner
vendor/bin/package bindings              # every interface → implementation, owner
```

Sample output of `vendor/bin/package show phpdot/http`:

```
phpdot/http
Advanced HTTP library for PHP. PSR-7/17 native. Framework-agnostic.
https://github.com/phpdot/http

Services
+-----------------------------+-----------+
| Class                       | Scope     |
+-----------------------------+-----------+
| PHPdot\Http\ResponseFactory | SINGLETON |
| PHPdot\Http\HttpConfig      | SINGLETON |
+-----------------------------+-----------+

Configs
+------------------------+-----------------+
| DTO                    | Config file     |
+------------------------+-----------------+
| PHPdot\Http\HttpConfig | config/http.php |
+------------------------+-----------------+

Bindings
+------------------------------------------------+-----------------------------+
| Interface                                      | Default implementation      |
+------------------------------------------------+-----------------------------+
| Psr\Http\Message\ResponseFactoryInterface      | PHPdot\Http\ResponseFactory |
| Psr\Http\Message\StreamFactoryInterface        | PHPdot\Http\ResponseFactory |
| Psr\Http\Message\UriFactoryInterface           | PHPdot\Http\ResponseFactory |
| ...                                            | ...                         |
+------------------------------------------------+-----------------------------+

How to override
  Globally: $builder->add(ResponseFactoryInterface::class, fn ($c) => new MyImpl())->singleton();
  Scoped to one consumer: $builder->when(MyController::class)->needs(ResponseFactoryInterface::class)->provide(MyImpl::class);
```

Project root resolution uses Composer's `\Composer\InstalledVersions::getRootPackage()['install_path']` — no path arithmetic, no environment guessing.

---

## PackageManager

One class. One constructor argument. Full control.

```php
use PHPdot\Package\PackageManager;

$manager = new PackageManager(__DIR__);

$manager->rebuild();              // scan + generate
$manager->clear();                // delete cached files
$manager->load($builder);         // load definitions into builder
$manager->manifest();             // read package metadata

$manager->basePath();             // /app
$manager->vendorPath();           // /app/vendor
$manager->configPath();           // /app/config
$manager->definitionsPath();      // /app/vendor/phpdot/definitions.php
$manager->manifestPath();         // /app/vendor/phpdot/manifest.php
```

### Application Boot

```php
$manager   = new PackageManager(__DIR__);
$builder   = $manager->load(new ContainerBuilder());
$container = $builder->build();
```

### Rebuild Result

```php
$result = $manager->rebuild();
// $result->packageCount       int
// $result->serviceCount       int
// $result->bindingCount       int   (number of #[Binds] declarations baked into definitions.php)
// $result->configCount        int
// $result->generatedConfigs   list<string>
// $result->orphanedConfigs    list<string>   paths previously owned but no longer backed by an installed package
```

### Manifest

```php
$manifest = $manager->manifest();
// $manifest->packageNames()
// $manifest->allServices()
// $manifest->allBindings()
// $manifest->allConfigs()
// $manifest->packages['phpdot/i18n']->description
// $manifest->packages['phpdot/i18n']->url
// $manifest->packages['phpdot/i18n']->author
```

---

## PackageScanner

Scans vendor packages or a specific directory for attributed classes.

```php
use PHPdot\Package\Scanner\PackageScanner;

$scanner = new PackageScanner();

// Scan all vendor packages
$result = $scanner->scan($vendorPath);
// $result->classes     list<ScannedClass>
// $result->packages    array<string, PackageMeta>

// Scan a specific directory (e.g. app src/)
$classes = $scanner->scanDirectory($directory, $namespace, $packageName);
// returns list<ScannedClass>
```

The scanner extracts package metadata from `installed.json`:

| Field | Source |
|-------|--------|
| `name` | `name` |
| `description` | `description` (fallback: `''`) |
| `url` | `support.source` or `homepage` (fallback: `''`) |
| `author` | `authors[0].name` + `authors[0].email` as `Name <email>` (fallback: `''`) |

For `#[Config]` classes, the scanner parses `@param` PHPDoc descriptions from the constructor docblock and stores them in `ScannedClass::$paramDescriptions`.

---

## Attributes Reference

| Attribute | Target | Effect |
|-----------|--------|--------|
| `#[Singleton]` | class | Registered as singleton in container |
| `#[Scoped]` | class | Fresh instance per request/coroutine |
| `#[Transient]` | class | New instance every resolution |
| `#[Config('name')]` | class | Singleton, hydrated from `config/{name}.php` via `Configuration::dto()` |
| `#[Binds(Interface::class)]` | class | Registers as default for interface (repeatable). Bakes into `definitions.php` — no separate scaffold file. |

---

## File Generation Rules

| File | Location | Regenerated | Overwritten |
|------|----------|-------------|-------------|
| Definitions | `vendor/phpdot/definitions.php` | Every Composer operation | Always |
| Manifest | `vendor/phpdot/manifest.php` | Every Composer operation | Always |
| Config | `config/{name}.php` | Once (first install) | Never |

Config files are owned by the developer. They are generated once and never overwritten, even if the package is updated. Removing the package from composer is reported on the next rebuild as an orphan; the developer decides whether to delete the file.

---

## Structure

```
src/
├── Cli/
│   ├── Application.php
│   └── Command/
│       ├── BindingsCommand.php
│       ├── ConfigListCommand.php
│       ├── ListCommand.php
│       ├── PathsCommand.php
│       ├── ServicesCommand.php
│       └── ShowCommand.php
├── Composer/
│   └── ComposerScript.php
├── Generator/
│   ├── ConfigFileGenerator.php
│   ├── DefinitionGenerator.php
│   └── ManifestGenerator.php
├── Scanner/
│   ├── PackageMeta.php
│   ├── PackageScanner.php
│   ├── ScanResult.php
│   └── ScannedClass.php
├── Manifest.php
├── PackageInfo.php
├── PackageManager.php
└── RebuildResult.php

bin/
└── package                  CLI entry point (Composer bin)
```

---

## API Reference

### PackageManager

```
__construct(string $basePath, array $environments = ['development', 'production', 'staging'])
load(ContainerBuilder $builder): ContainerBuilder
rebuild(): RebuildResult
clear(): void
manifest(): ?Manifest
basePath(): string
vendorPath(): string
configPath(): string
definitionsPath(): string
manifestPath(): string
```

### PackageScanner

```
scan(string $vendorPath, list<string> $exclude = []): ScanResult
scanDirectory(string $directory, string $namespace, string $package): list<ScannedClass>
```

### ScanResult

```
list<ScannedClass> $classes
array<string, PackageMeta> $packages
```

### ScannedClass

```
string $class
Scope $scope
list<class-string> $params
list<class-string> $binds
?string $configName
string $package
array<string, string> $paramDescriptions
```

### PackageMeta

```
string $name
string $description
string $url
string $author
```

### Manifest

```
array<string, PackageInfo> $packages
string $generatedAt
packageNames(): list<string>
allServices(): array<string, string>
allBindings(): array<string, string>
allConfigs(): array<string, string>
```

### PackageInfo

```
string $name
string $description
string $url
string $author
array<string, string> $services
array<string, string> $configs
array<string, string> $bindings
```

### RebuildResult

```
int $packageCount
int $serviceCount
int $bindingCount
int $configCount
list<string> $generatedConfigs
list<string> $orphanedConfigs
```

### ConfigFileGenerator

```
generate(array $classes, array $packages, string $configPath, array $environments = [...]): list<string>
ownedPaths(array $classes, string $configPath): list<string>
```

### DefinitionGenerator

```
generate(array $classes, array $packages = []): string
```

### ManifestGenerator

```
generate(array $classes, array $packages = [], list<string> $ownedConfigs = []): string
```

---

## License

MIT
