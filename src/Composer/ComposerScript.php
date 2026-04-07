<?php

declare(strict_types=1);

/**
 * Composer Script
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Package\Composer;

use Composer\Script\Event;
use PHPdot\Package\PackageManager;

final class ComposerScript
{
    public static function postAutoloadDump(Event $event): void
    {
        $composer = $event->getComposer();

        /** @var string $vendorDir */
        $vendorDir = $composer->getConfig()->get('vendor-dir');
        $basePath = dirname($vendorDir);

        /** @var array<string, mixed> $extra */
        $extra = $composer->getPackage()->getExtra();
        $phpdot = is_array($extra['phpdot'] ?? null) ? $extra['phpdot'] : [];
        $configDir = is_string($phpdot['config-dir'] ?? null) ? $phpdot['config-dir'] : 'config';
        $containerDir = is_string($phpdot['container-dir'] ?? null) ? $phpdot['container-dir'] : 'container';
        $configPath = $basePath . '/' . $configDir;
        $containerPath = $basePath . '/' . $containerDir;

        $manager = new PackageManager($vendorDir, $configPath, $containerPath);
        $result = $manager->rebuild();

        $io = $event->getIO();
        $io->write(sprintf(
            '<info>phpdot/package:</info> %d package%s, %d service%s, %d binding%s cached.',
            $result->packageCount,
            $result->packageCount === 1 ? '' : 's',
            $result->serviceCount,
            $result->serviceCount === 1 ? '' : 's',
            $result->bindingCount,
            $result->bindingCount === 1 ? '' : 's',
        ));

        foreach ($result->generatedConfigs as $path) {
            $relative = str_replace($basePath . '/', '', $path);
            $io->write(sprintf('<info>phpdot/package:</info> generated %s', $relative));
        }

        foreach ($result->generatedBindings as $path) {
            $relative = str_replace($basePath . '/', '', $path);
            $io->write(sprintf('<info>phpdot/package:</info> generated %s', $relative));
        }
    }
}
