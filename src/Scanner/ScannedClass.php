<?php

declare(strict_types=1);

/**
 * Scanned Class
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Package\Scanner;

use PHPdot\Container\Scope;

/**
 * @internal Created by PackageScanner, consumed by generators.
 */
final readonly class ScannedClass
{
    /**
     * @param class-string $class Fully qualified class name
     * @param Scope $scope Container scope
     * @param list<class-string> $params Constructor param type FQCNs (classes/interfaces only)
     * @param list<class-string> $binds Interface FQCNs from #[Binds]
     * @param string|null $configName Config file name from #[Config], or null
     * @param string $package Composer package name
     */
    public function __construct(
        public string $class,
        public Scope $scope,
        public array $params,
        public array $binds,
        public ?string $configName,
        public string $package,
    ) {
    }
}
