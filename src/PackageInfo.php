<?php

declare(strict_types=1);

/**
 * Package Info
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Package;

final readonly class PackageInfo
{
    /**
     * @param string $name Composer package name
     * @param array<string, string> $services class => scope string
     * @param array<string, string> $configs class => config name
     * @param array<string, string> $bindings interface => implementation
     * @param list<string> $allClasses every scanned class
     */
    public function __construct(
        public string $name,
        public array $services,
        public array $configs,
        public array $bindings,
        public array $allClasses,
    ) {
    }
}
