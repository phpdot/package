<?php

declare(strict_types=1);

/**
 * Package Bootstrap
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Package;

use PHPdot\Container\ContainerBuilder;

final class PackageBootstrap
{
    public static function load(string $vendorPath, ContainerBuilder $builder): void
    {
        $path = $vendorPath . '/phpdot/definitions.php';

        if (!is_file($path)) {
            return;
        }

        /** @var array<string, mixed> $definitions */
        $definitions = require $path;

        $builder->addDefinitions($definitions);
    }
}
