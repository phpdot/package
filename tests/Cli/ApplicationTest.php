<?php

declare(strict_types=1);

namespace PHPdot\Package\Tests\Cli;

use PHPdot\Package\Cli\Application;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ApplicationTest extends TestCase
{
    #[Test]
    public function it_registers_all_six_commands(): void
    {
        $app = new Application(sys_get_temp_dir());

        self::assertTrue($app->has('list'));
        self::assertTrue($app->has('show'));
        self::assertTrue($app->has('paths'));
        self::assertTrue($app->has('config:list'));
        self::assertTrue($app->has('services'));
        self::assertTrue($app->has('bindings'));
    }
}
