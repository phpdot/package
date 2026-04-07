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
        /** @var string $vendorDir */
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
        $basePath = dirname($vendorDir);

        $manager = new PackageManager($basePath);
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
