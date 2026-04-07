<?php

declare(strict_types=1);

namespace PHPdot\Package\Tests\Fixtures;

use PHPdot\Container\Attribute\Config;

#[Config('sample')]
final readonly class SampleConfig
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        public string $name = 'default',
        public int $port = 3000,
        public bool $debug = false,
        public array $tags = [],
    ) {
    }
}
