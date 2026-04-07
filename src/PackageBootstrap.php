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
    public static function load(string $basePath, ContainerBuilder $builder): void
    {
        $cachePath = $basePath . '/var/cache/phpdot-definitions.php';

        if (!is_file($cachePath)) {
            return;
        }

        /** @var array<string, mixed> $definitions */
        $definitions = require $cachePath;

        $builder->addDefinitions($definitions);
    }
}
