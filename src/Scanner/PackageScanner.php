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
     * @return list<ScannedClass>
     */
    public function scan(string $vendorPath): array
    {
        $installedPath = $vendorPath . '/composer/installed.json';

        if (!is_file($installedPath)) {
            return [];
        }

        $content = file_get_contents($installedPath);

        if ($content === false) {
            return [];
        }

        /** @var array<string, mixed> $installed */
        $installed = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        /** @var list<array<string, mixed>> $packages */
        $packages = isset($installed['packages']) && is_array($installed['packages'])
            ? $installed['packages']
            : [];

        $results = [];

        foreach ($packages as $meta) {
            $name = $meta['name'] ?? null;
            if (!is_string($name)) {
                continue;
            }

            if ($name === self::CONTAINER_PACKAGE) {
                continue;
            }

            $require = is_array($meta['require'] ?? null) ? $meta['require'] : [];
            $requireDev = is_array($meta['require-dev'] ?? null) ? $meta['require-dev'] : [];

            if (!isset($require[self::CONTAINER_PACKAGE]) && !isset($requireDev[self::CONTAINER_PACKAGE])) {
                continue;
            }

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

        return $results;
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

        return new ScannedClass(
            class: $class,
            scope: $scope,
            params: $params,
            binds: $binds,
            configName: $configName,
            package: $package,
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
