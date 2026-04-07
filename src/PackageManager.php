<?php

declare(strict_types=1);

/**
 * Package Manager
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Package;

use PHPdot\Package\Generator\ConfigFileGenerator;
use PHPdot\Package\Generator\DefinitionGenerator;
use PHPdot\Package\Generator\ManifestGenerator;
use PHPdot\Package\Scanner\PackageScanner;

final class PackageManager
{
    private const string PHPDOT_DIR = 'phpdot';
    private const string DEFINITIONS_FILE = 'definitions.php';
    private const string MANIFEST_FILE = 'manifest.php';

    public function __construct(
        private readonly string $vendorPath,
        private readonly string $configPath,
    ) {
    }

    public function rebuild(): RebuildResult
    {
        $scanner = new PackageScanner();
        $classes = $scanner->scan($this->vendorPath);

        $defGenerator = new DefinitionGenerator();
        $defContent = $defGenerator->generate($classes);
        $this->writePhpdotFile(self::DEFINITIONS_FILE, $defContent);

        $manifestGenerator = new ManifestGenerator();
        $manifestContent = $manifestGenerator->generate($classes);
        $this->writePhpdotFile(self::MANIFEST_FILE, $manifestContent);

        $configGenerator = new ConfigFileGenerator();
        $generatedConfigs = $configGenerator->generate($classes, $this->configPath);

        $bindingCount = 0;
        $configCount = 0;
        $packageNames = [];

        foreach ($classes as $scanned) {
            $bindingCount += count($scanned->binds);

            if ($scanned->configName !== null) {
                $configCount++;
            }

            $packageNames[$scanned->package] = true;
        }

        return new RebuildResult(
            packageCount: count($packageNames),
            serviceCount: count($classes),
            bindingCount: $bindingCount,
            configCount: $configCount,
            generatedConfigs: $generatedConfigs,
        );
    }

    public function clear(): void
    {
        $dir = $this->phpdotDir();

        $defPath = $dir . '/' . self::DEFINITIONS_FILE;

        if (is_file($defPath)) {
            unlink($defPath);
        }

        $manifestPath = $dir . '/' . self::MANIFEST_FILE;

        if (is_file($manifestPath)) {
            unlink($manifestPath);
        }
    }

    public function manifest(): ?Manifest
    {
        $path = $this->phpdotDir() . '/' . self::MANIFEST_FILE;

        if (!is_file($path)) {
            return null;
        }

        /** @var array{generated_at: string, packages: array<string, array{services: array<string, string>, configs: array<string, string>, bindings: array<string, string>, classes: list<string>}>} $data */
        $data = require $path;

        $packages = [];

        foreach ($data['packages'] as $name => $info) {
            $packages[$name] = new PackageInfo(
                name: $name,
                services: $info['services'],
                configs: $info['configs'],
                bindings: $info['bindings'],
                allClasses: $info['classes'],
            );
        }

        return new Manifest(
            packages: $packages,
            generatedAt: $data['generated_at'],
        );
    }

    public function definitionsPath(): string
    {
        return $this->phpdotDir() . '/' . self::DEFINITIONS_FILE;
    }

    public function manifestPath(): string
    {
        return $this->phpdotDir() . '/' . self::MANIFEST_FILE;
    }

    private function phpdotDir(): string
    {
        return $this->vendorPath . '/' . self::PHPDOT_DIR;
    }

    private function writePhpdotFile(string $filename, string $content): void
    {
        $dir = $this->phpdotDir();

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = $dir . '/' . $filename;
        $tmp = $path . '.tmp.' . getmypid();
        file_put_contents($tmp, $content);
        rename($tmp, $path);

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($path, true);
        }
    }
}
