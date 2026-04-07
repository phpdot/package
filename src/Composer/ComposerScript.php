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
use PHPdot\Package\Generator\ConfigFileGenerator;
use PHPdot\Package\Generator\DefinitionGenerator;
use PHPdot\Package\Scanner\PackageScanner;

final class ComposerScript
{
    public static function postAutoloadDump(Event $event): void
    {
        /** @var string $vendorDir */
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
        $basePath = dirname($vendorDir);
        $cachePath = $basePath . '/var/cache/phpdot-definitions.php';
        $configPath = $basePath . '/config';

        $scanner = new PackageScanner();
        $classes = $scanner->scan($vendorDir);

        $defGenerator = new DefinitionGenerator();
        $content = $defGenerator->generate($classes);

        $cacheDir = dirname($cachePath);

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $tmp = $cachePath . '.tmp.' . getmypid();
        file_put_contents($tmp, $content);
        rename($tmp, $cachePath);

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($cachePath, true);
        }

        $configGenerator = new ConfigFileGenerator();
        $generated = $configGenerator->generate($classes, $configPath);

        $io = $event->getIO();
        $io->write(sprintf(
            '<info>phpdot/package:</info> %d services cached, %d config files generated.',
            count($classes),
            count($generated),
        ));
    }
}
