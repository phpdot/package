<?php

declare(strict_types=1);

/**
 * Definition Generator
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Package\Generator;

use PHPdot\Container\Scope;
use PHPdot\Package\Scanner\PackageMeta;
use PHPdot\Package\Scanner\ScannedClass;

final class DefinitionGenerator
{
    /**
     * @param list<ScannedClass> $classes
     * @param array<string, PackageMeta> $packages
     */
    public function generate(array $classes, array $packages = []): string
    {
        $lines = [];
        $lines[] = "<?php\n";
        $lines[] = $this->generateHeader();
        $lines[] = "\ndeclare(strict_types=1);\n";
        $lines[] = "\nuse PHPdot\\Container\\Definition\\ScopedDefinition;";
        $lines[] = "\nuse PHPdot\\Container\\Scope;";
        $lines[] = "\nuse Psr\\Container\\ContainerInterface;\n";
        $lines[] = "\nreturn [\n";

        $grouped = $this->groupByPackage($classes);

        foreach ($grouped as $package => $group) {
            $meta = $packages[$package] ?? null;
            $lines[] = $this->generatePackageHeader($package, $meta);

            foreach ($group as $scanned) {
                $lines[] = $this->generateClass($scanned);

                foreach ($scanned->binds as $interface) {
                    $lines[] = $this->generateBinding($scanned, $interface);
                }
            }
        }

        $lines[] = "\n];\n";

        return implode('', $lines);
    }

    private function generateHeader(): string
    {
        $timestamp = date('c');

        return <<<PHP

            /**
             * PHPdot Container Definitions
             *
             * @generated   phpdot/package
             * @date        {$timestamp}
             * @see         https://github.com/phpdot/package
             *
             * Regenerated on every composer install/update/require/remove.
             * Do not edit — changes will be overwritten.
             */

            PHP;
    }

    private function generatePackageHeader(string $package, ?PackageMeta $meta): string
    {
        $lines = "\n    /**\n";
        $lines .= "     * {$package}\n";

        if ($meta !== null && $meta->description !== '') {
            $lines .= "     * {$meta->description}\n";
        }

        if ($meta !== null && $meta->url !== '') {
            $lines .= "     *\n";
            $lines .= "     * @see {$meta->url}\n";
        }

        $lines .= "     */\n";

        return $lines;
    }

    private function generateClass(ScannedClass $scanned): string
    {
        $fqcn = $this->fqcn($scanned->class);
        $scope = $this->scopeString($scanned->scope);

        if ($scanned->configName !== null) {
            return <<<PHP

                    {$fqcn}::class => new ScopedDefinition(
                        scope: {$scope},
                        factory: static fn (ContainerInterface \$c): {$fqcn}
                            => \$c->get(\\PHPdot\\Config\\Configuration::class)->dto('{$scanned->configName}', {$fqcn}::class),
                    ),

                PHP;
        }

        if ($scanned->params === []) {
            return <<<PHP

                    {$fqcn}::class => new ScopedDefinition(
                        scope: {$scope},
                    ),

                PHP;
        }

        $gets = [];
        foreach ($scanned->params as $param) {
            $gets[] = "            \$c->get({$this->fqcn($param)}::class),";
        }
        $getLines = implode("\n", $gets);

        return <<<PHP

                {$fqcn}::class => new ScopedDefinition(
                    scope: {$scope},
                    factory: static fn (ContainerInterface \$c): {$fqcn}
                        => new {$fqcn}(
            {$getLines}
                        ),
                ),

            PHP;
    }

    private function generateBinding(ScannedClass $scanned, string $interface): string
    {
        $ifqcn = $this->fqcn($interface);
        $cfqcn = $this->fqcn($scanned->class);
        $scope = $this->scopeString($scanned->scope);

        return <<<PHP

                {$ifqcn}::class => new ScopedDefinition(
                    scope: {$scope},
                    factory: static fn (ContainerInterface \$c): {$ifqcn}
                        => \$c->get({$cfqcn}::class),
                ),

            PHP;
    }

    private function scopeString(Scope $scope): string
    {
        return match ($scope) {
            Scope::SINGLETON => 'Scope::SINGLETON',
            Scope::SCOPED => 'Scope::SCOPED',
            Scope::TRANSIENT => 'Scope::TRANSIENT',
        };
    }

    private function fqcn(string $class): string
    {
        return '\\' . ltrim($class, '\\');
    }

    /**
     * @param list<ScannedClass> $classes
     * @return array<string, list<ScannedClass>>
     */
    private function groupByPackage(array $classes): array
    {
        $grouped = [];

        foreach ($classes as $scanned) {
            $grouped[$scanned->package][] = $scanned;
        }

        return $grouped;
    }
}
