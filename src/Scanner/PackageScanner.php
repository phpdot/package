<?php

declare(strict_types=1);

/**
 * Package Scanner
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Package\Scanner;

use PHPdot\Container\Attribute\Binds;
use PHPdot\Container\Attribute\Config;
use PHPdot\Container\Attribute\Scoped;
use PHPdot\Container\Attribute\Singleton;
use PHPdot\Container\Attribute\Transient;
use PHPdot\Container\Scope;
use ReflectionClass;
use ReflectionNamedType;

final class PackageScanner
{
    private const string CONTAINER_PACKAGE = 'phpdot/container';

    /**
     * @param list<string> $exclude Package names to skip
     */
    public function scan(string $vendorPath, array $exclude = []): ScanResult
    {
        $installedPath = $vendorPath . '/composer/installed.json';

        if (!is_file($installedPath)) {
            return new ScanResult([], []);
        }

        $content = file_get_contents($installedPath);

        if ($content === false) {
            return new ScanResult([], []);
        }

        /** @var array<string, mixed> $installed */
        $installed = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        /** @var list<array<string, mixed>> $packages */
        $packages = isset($installed['packages']) && is_array($installed['packages'])
            ? $installed['packages']
            : [];

        $results = [];
        $packagesMeta = [];

        foreach ($packages as $meta) {
            $name = $meta['name'] ?? null;
            if (!is_string($name)) {
                continue;
            }

            if ($name === self::CONTAINER_PACKAGE) {
                continue;
            }

            if (in_array($name, $exclude, true)) {
                continue;
            }

            $require = is_array($meta['require'] ?? null) ? $meta['require'] : [];
            $requireDev = is_array($meta['require-dev'] ?? null) ? $meta['require-dev'] : [];

            if (!isset($require[self::CONTAINER_PACKAGE]) && !isset($requireDev[self::CONTAINER_PACKAGE])) {
                continue;
            }

            $packagesMeta[$name] = $this->extractMeta($name, $meta);

            $installPath = $meta['install-path'] ?? null;
            if (!is_string($installPath)) {
                continue;
            }

            $absolutePath = realpath($vendorPath . '/composer/' . $installPath);
            if ($absolutePath === false) {
                continue;
            }

            $autoload = $meta['autoload'] ?? [];
            if (!is_array($autoload)) {
                continue;
            }

            $psr4 = $autoload['psr-4'] ?? [];
            if (!is_array($psr4)) {
                continue;
            }

            foreach ($psr4 as $namespace => $srcDir) {
                if (!is_string($namespace) || !is_string($srcDir)) {
                    continue;
                }

                $fullSrcDir = $absolutePath . '/' . trim($srcDir, '/');

                if (!is_dir($fullSrcDir)) {
                    continue;
                }

                $scanned = $this->scanDirectory($fullSrcDir, $namespace, $name);
                $results = [...$results, ...$scanned];
            }
        }

        return new ScanResult($results, $packagesMeta);
    }

    /**
     * @return list<ScannedClass>
     */
    public function scanDirectory(string $directory, string $namespace, string $package): array
    {
        $files = $this->findPhpFiles($directory);
        $results = [];

        foreach ($files as $file) {
            $class = $this->resolveClassName($file, $directory, $namespace);

            if ($class === null) {
                continue;
            }

            if (!class_exists($class)) {
                continue;
            }

            $scanned = $this->scanClass($class, $package);

            if ($scanned !== null) {
                $results[] = $scanned;
            }
        }

        return $results;
    }

    /**
     * @param class-string $class
     */
    private function scanClass(string $class, string $package): ?ScannedClass
    {
        $ref = new ReflectionClass($class);

        if ($ref->isAbstract() || $ref->isInterface() || $ref->isTrait() || $ref->isEnum()) {
            return null;
        }

        $scope = $this->resolveScope($ref);

        if ($scope === null) {
            return null;
        }

        $params = $this->resolveParams($ref);

        $binds = [];
        foreach ($ref->getAttributes(Binds::class) as $attr) {
            $binds[] = $attr->newInstance()->interface;
        }

        $configAttrs = $ref->getAttributes(Config::class);
        $configName = $configAttrs !== [] ? $configAttrs[0]->newInstance()->name : null;

        $paramDescriptions = $configName !== null ? $this->parseParamDescriptions($ref) : [];

        return new ScannedClass(
            class: $class,
            scope: $scope,
            params: $params,
            binds: $binds,
            configName: $configName,
            package: $package,
            paramDescriptions: $paramDescriptions,
        );
    }

    /**
     * @param ReflectionClass<object> $ref
     */
    private function resolveScope(ReflectionClass $ref): ?Scope
    {
        if ($ref->getAttributes(Singleton::class) !== []) {
            return Scope::SINGLETON;
        }

        if ($ref->getAttributes(Scoped::class) !== []) {
            return Scope::SCOPED;
        }

        if ($ref->getAttributes(Transient::class) !== []) {
            return Scope::TRANSIENT;
        }

        if ($ref->getAttributes(Config::class) !== []) {
            return Scope::SINGLETON;
        }

        return null;
    }

    /**
     * @param ReflectionClass<object> $ref
     * @return list<class-string>
     */
    private function resolveParams(ReflectionClass $ref): array
    {
        $constructor = $ref->getConstructor();

        if ($constructor === null) {
            return [];
        }

        $params = [];

        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();

            if (!$type instanceof ReflectionNamedType) {
                continue;
            }

            if ($type->isBuiltin()) {
                continue;
            }

            /** @var class-string $typeName */
            $typeName = $type->getName();
            $params[] = $typeName;
        }

        return $params;
    }

    /**
     * Parse @param descriptions from a constructor's docblock.
     *
     * @param ReflectionClass<object> $ref
     * @return array<string, string> Parameter name => description
     */
    private function parseParamDescriptions(ReflectionClass $ref): array
    {
        $constructor = $ref->getConstructor();

        if ($constructor === null) {
            return [];
        }

        $doc = $constructor->getDocComment();

        if ($doc === false) {
            return [];
        }

        $descriptions = [];

        preg_match_all(
            '/@param\s+\S+\s+\$(\w+)\s+(.+)/m',
            $doc,
            $matches,
            PREG_SET_ORDER,
        );

        foreach ($matches as $match) {
            $descriptions[$match[1]] = trim($match[2]);
        }

        return $descriptions;
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function extractMeta(string $name, array $meta): PackageMeta
    {
        $description = is_string($meta['description'] ?? null) ? $meta['description'] : '';

        $url = '';
        $support = $meta['support'] ?? [];
        if (is_array($support) && is_string($support['source'] ?? null)) {
            $url = $support['source'];
        } elseif (is_string($meta['homepage'] ?? null)) {
            $url = $meta['homepage'];
        }

        $author = '';
        $authors = $meta['authors'] ?? [];
        if (is_array($authors) && isset($authors[0]) && is_array($authors[0])) {
            $authorName = is_string($authors[0]['name'] ?? null) ? $authors[0]['name'] : '';
            $authorEmail = is_string($authors[0]['email'] ?? null) ? $authors[0]['email'] : '';
            $author = $authorEmail !== '' ? "{$authorName} <{$authorEmail}>" : $authorName;
        }

        return new PackageMeta(
            name: $name,
            description: $description,
            url: $url,
            author: $author,
        );
    }

    /**
     * @return list<string>
     */
    private function findPhpFiles(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function resolveClassName(string $file, string $baseDir, string $namespace): ?string
    {
        $relative = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $file);
        $relative = str_replace(DIRECTORY_SEPARATOR, '\\', $relative);
        $relative = preg_replace('/\.php$/', '', $relative);

        if (!is_string($relative)) {
            return null;
        }

        return $namespace . $relative;
    }
}
