<?php

declare(strict_types=1);

/**
 * Package Meta
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Package\Scanner;

/**
 * Per-package metadata extracted from installed.json.
 *
 * @internal Extracted from installed.json by PackageScanner.
 */
final readonly class PackageMeta
{
    /**
     * @param string $name Composer package name
     * @param string $description Package description
     * @param string $url Package source URL
     * @param string $author Author formatted as "Name <email>"
     */
    public function __construct(
        public string $name,
        public string $description = '',
        public string $url = '',
        public string $author = '',
    ) {}
}
