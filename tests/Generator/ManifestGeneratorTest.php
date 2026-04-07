<?php

declare(strict_types=1);

namespace PHPdot\Package\Tests\Generator;

use PHPdot\Container\Scope;
use PHPdot\Package\Generator\ManifestGenerator;
use PHPdot\Package\Scanner\ScannedClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ManifestGeneratorTest extends TestCase
{
    private ManifestGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new ManifestGenerator();
    }

    #[Test]
    public function it_generates_valid_php(): void
    {
        $output = $this->generator->generate([
            new ScannedClass('App\\Svc', Scope::SINGLETON, [], [], null, 'app/pkg'),
        ]);

        $tokens = token_get_all($output);
        self::assertNotEmpty($tokens);
    }

    #[Test]
    public function it_groups_by_package(): void
    {
        $output = $this->generator->generate([
            new ScannedClass('A\\Svc', Scope::SINGLETON, [], [], null, 'pkg/a'),
            new ScannedClass('B\\Svc', Scope::SCOPED, [], [], null, 'pkg/b'),
        ]);

        self::assertStringContainsString('pkg/a', $output);
        self::assertStringContainsString('pkg/b', $output);
    }

    #[Test]
    public function it_includes_services(): void
    {
        $output = $this->generator->generate([
            new ScannedClass('App\\MySvc', Scope::SINGLETON, [], [], null, 'app/pkg'),
        ]);

        self::assertStringContainsString('App\\\\MySvc', $output);
        self::assertStringContainsString('SINGLETON', $output);
    }

    #[Test]
    public function it_includes_configs(): void
    {
        $output = $this->generator->generate([
            new ScannedClass('App\\Cfg', Scope::SINGLETON, [], [], 'myapp', 'app/pkg'),
        ]);

        self::assertStringContainsString('myapp', $output);
    }

    #[Test]
    public function it_includes_bindings(): void
    {
        $output = $this->generator->generate([
            new ScannedClass('App\\Impl', Scope::SINGLETON, [], ['App\\Iface'], null, 'app/pkg'),
        ]);

        self::assertStringContainsString('App\\\\Iface', $output);
        self::assertStringContainsString('App\\\\Impl', $output);
    }

    #[Test]
    public function it_includes_timestamp(): void
    {
        $output = $this->generator->generate([]);

        self::assertStringContainsString('generated_at', $output);
    }

    #[Test]
    public function it_handles_empty_input(): void
    {
        $output = $this->generator->generate([]);

        self::assertStringContainsString('return [', $output);
        $tokens = token_get_all($output);
        self::assertNotEmpty($tokens);
    }
}
