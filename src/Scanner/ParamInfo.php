<?php

declare(strict_types=1);

/**
 * Param Info
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Package\Scanner;

/**
 * PHPDoc description for a constructor parameter.
 *
 * @internal
 */
final readonly class ParamInfo
{
    /**
     * @param string $name Parameter name
     * @param string $type Parameter type
     * @param string $description PHPDoc description
     * @param mixed $default Default value
     * @param bool $hasDefault Whether a default value exists
     */
    public function __construct(
        public string $name,
        public string $type,
        public string $description,
        public mixed $default,
        public bool $hasDefault,
    ) {}
}
