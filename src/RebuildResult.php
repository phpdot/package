<?php

declare(strict_types=1);

/**
 * Rebuild Result
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Package;

final readonly class RebuildResult
{
    /**
     * @param int $packageCount Packages discovered
     * @param int $serviceCount Services registered
     * @param int $bindingCount Interface bindings
     * @param int $configCount Config DTOs found
     * @param list<string> $generatedConfigs Paths of newly generated config files
     */
    public function __construct(
        public int $packageCount,
        public int $serviceCount,
        public int $bindingCount,
        public int $configCount,
        public array $generatedConfigs,
    ) {
    }
}
