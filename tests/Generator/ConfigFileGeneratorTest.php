<?php

declare(strict_types=1);

namespace PHPdot\Package\Tests\Generator;

use PHPdot\Container\Scope;
use PHPdot\Package\Generator\ConfigFileGenerator;
use PHPdot\Package\Scanner\ScannedClass;
use PHPdot\Package\Tests\Fixtures\SampleConfig;
use PHPdot\Package\Tests\Fixtures\SampleInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConfigFileGeneratorTest extends TestCase
{
    private ConfigFileGenerator $generator;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->generator = new ConfigFileGenerator();
        $this->tmpDir = sys_get_temp_dir() . '/phpdot_cfggen_' . uniqid();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    #[Test]
    public function it_generates_config_file(): void
    {
        $classes = [
            new ScannedClass(SampleConfig::class, Scope::SINGLETON, [], [], 'sample', 'test/pkg'),
        ];

        $generated = $this->generator->generate($classes, $this->tmpDir);

        self::assertCount(1, $generated);
        self::assertFileExists($this->tmpDir . '/sample.php');
    }

    #[Test]
    public function it_contains_parameter_keys_with_defaults(): void
    {
        $classes = [
            new ScannedClass(SampleConfig::class, Scope::SINGLETON, [], [], 'sample', 'test/pkg'),
        ];

        $this->generator->generate($classes, $this->tmpDir);

        $content = file_get_contents($this->tmpDir . '/sample.php');
        self::assertIsString($content);
        self::assertStringContainsString("'name' => 'default'", $content);
        self::assertStringContainsString("'port' => 3000", $content);
        self::assertStringContainsString("'debug' => false", $content);
        self::assertStringContainsString("'tags' => []", $content);
    }

    #[Test]
    public function it_contains_type_comments(): void
    {
        $classes = [
            new ScannedClass(SampleConfig::class, Scope::SINGLETON, [], [], 'sample', 'test/pkg'),
        ];

        $this->generator->generate($classes, $this->tmpDir);

        $content = file_get_contents($this->tmpDir . '/sample.php');
        self::assertIsString($content);
        self::assertStringContainsString('(string)', $content);
        self::assertStringContainsString('(int)', $content);
        self::assertStringContainsString('(bool)', $content);
        self::assertStringContainsString('(array)', $content);
    }

    #[Test]
    public function it_skips_existing_config_files(): void
    {
        mkdir($this->tmpDir, 0755, true);
        file_put_contents($this->tmpDir . '/sample.php', '<?php return [];');

        $classes = [
            new ScannedClass(SampleConfig::class, Scope::SINGLETON, [], [], 'sample', 'test/pkg'),
        ];

        $generated = $this->generator->generate($classes, $this->tmpDir);

        self::assertSame([], $generated);

        $content = file_get_contents($this->tmpDir . '/sample.php');
        self::assertSame('<?php return [];', $content);
    }

    #[Test]
    public function it_skips_non_config_classes(): void
    {
        $classes = [
            new ScannedClass('App\\Svc', Scope::SINGLETON, [], [], null, 'test/pkg'),
        ];

        $generated = $this->generator->generate($classes, $this->tmpDir);

        self::assertSame([], $generated);
    }

    #[Test]
    public function it_includes_package_and_class_in_header(): void
    {
        $classes = [
            new ScannedClass(SampleConfig::class, Scope::SINGLETON, [], [], 'sample', 'test/pkg'),
        ];

        $this->generator->generate($classes, $this->tmpDir);

        $content = file_get_contents($this->tmpDir . '/sample.php');
        self::assertIsString($content);
        self::assertStringContainsString('test/pkg', $content);
        self::assertStringContainsString('SampleConfig', $content);
    }

    #[Test]
    public function it_generates_valid_php(): void
    {
        $classes = [
            new ScannedClass(SampleConfig::class, Scope::SINGLETON, [], [], 'sample', 'test/pkg'),
        ];

        $this->generator->generate($classes, $this->tmpDir);

        /** @var array<string, mixed> $result */
        $result = require $this->tmpDir . '/sample.php';

        self::assertIsArray($result);
        self::assertArrayHasKey('name', $result);
        self::assertArrayHasKey('port', $result);
        self::assertSame('default', $result['name']);
        self::assertSame(3000, $result['port']);
    }

    #[Test]
    public function it_creates_config_directory(): void
    {
        $deepDir = $this->tmpDir . '/nested/config';

        $classes = [
            new ScannedClass(SampleConfig::class, Scope::SINGLETON, [], [], 'sample', 'test/pkg'),
        ];

        $this->generator->generate($classes, $deepDir);

        self::assertDirectoryExists($deepDir);
        self::assertFileExists($deepDir . '/sample.php');
    }

    #[Test]
    public function it_includes_service_list_in_hint(): void
    {
        $classes = [
            new ScannedClass(SampleConfig::class, Scope::SINGLETON, [], [], 'sample', 'test/pkg'),
            new ScannedClass('PHPdot\\Package\\Tests\\Fixtures\\SimpleService', Scope::SINGLETON, [], [], null, 'test/pkg'),
            new ScannedClass('PHPdot\\Package\\Tests\\Fixtures\\SampleService', Scope::SCOPED, [SampleInterface::class, SampleConfig::class], [], null, 'test/pkg'),
        ];

        $this->generator->generate($classes, $this->tmpDir);

        $content = file_get_contents($this->tmpDir . '/sample.php');
        self::assertIsString($content);
        self::assertStringContainsString('Services:', $content);
        self::assertStringContainsString('SampleConfig', $content);
        self::assertStringContainsString('SimpleService', $content);
        self::assertStringContainsString('SampleService', $content);
    }

    #[Test]
    public function it_includes_scope_per_service(): void
    {
        $classes = [
            new ScannedClass(SampleConfig::class, Scope::SINGLETON, [], [], 'sample', 'test/pkg'),
            new ScannedClass('PHPdot\\Package\\Tests\\Fixtures\\SampleService', Scope::SCOPED, [], [], null, 'test/pkg'),
        ];

        $this->generator->generate($classes, $this->tmpDir);

        $content = file_get_contents($this->tmpDir . '/sample.php');
        self::assertIsString($content);
        self::assertStringContainsString('singleton', $content);
        self::assertStringContainsString('scoped', $content);
    }

    #[Test]
    public function it_includes_binding_info(): void
    {
        $classes = [
            new ScannedClass(SampleConfig::class, Scope::SINGLETON, [], [], 'sample', 'test/pkg'),
            new ScannedClass('PHPdot\\Package\\Tests\\Fixtures\\SampleLoader', Scope::SINGLETON, [SampleConfig::class], [SampleInterface::class], null, 'test/pkg'),
        ];

        $this->generator->generate($classes, $this->tmpDir);

        $content = file_get_contents($this->tmpDir . '/sample.php');
        self::assertIsString($content);
        self::assertStringContainsString('binds SampleInterface', $content);
    }

    #[Test]
    public function it_includes_override_example(): void
    {
        $classes = [
            new ScannedClass(SampleConfig::class, Scope::SINGLETON, [], [], 'sample', 'test/pkg'),
            new ScannedClass('PHPdot\\Package\\Tests\\Fixtures\\SampleLoader', Scope::SINGLETON, [], [SampleInterface::class], null, 'test/pkg'),
        ];

        $this->generator->generate($classes, $this->tmpDir);

        $content = file_get_contents($this->tmpDir . '/sample.php');
        self::assertIsString($content);
        self::assertStringContainsString('container/services.php', $content);
        self::assertStringContainsString('SampleInterface::class', $content);
    }

    #[Test]
    public function it_includes_contextual_binding_example(): void
    {
        $classes = [
            new ScannedClass(SampleConfig::class, Scope::SINGLETON, [], [], 'sample', 'test/pkg'),
            new ScannedClass('PHPdot\\Package\\Tests\\Fixtures\\SampleService', Scope::SCOPED, [SampleInterface::class, SampleConfig::class], [], null, 'test/pkg'),
        ];

        $this->generator->generate($classes, $this->tmpDir);

        $content = file_get_contents($this->tmpDir . '/sample.php');
        self::assertIsString($content);
        self::assertStringContainsString('container/bindings.php', $content);
        self::assertStringContainsString('->needs(', $content);
        self::assertStringContainsString('->provide(', $content);
    }

    #[Test]
    public function it_uses_short_class_names_in_hints(): void
    {
        $classes = [
            new ScannedClass(SampleConfig::class, Scope::SINGLETON, [], [], 'sample', 'test/pkg'),
            new ScannedClass('PHPdot\\Package\\Tests\\Fixtures\\SampleLoader', Scope::SINGLETON, [SampleConfig::class], [SampleInterface::class], null, 'test/pkg'),
        ];

        $this->generator->generate($classes, $this->tmpDir);

        $content = file_get_contents($this->tmpDir . '/sample.php');
        self::assertIsString($content);
        self::assertStringContainsString('SampleLoader', $content);
        self::assertStringNotContainsString('PHPdot\\Package\\Tests\\Fixtures\\SampleLoader', $content);
    }

    #[Test]
    public function it_generates_config_with_no_siblings(): void
    {
        $classes = [
            new ScannedClass(SampleConfig::class, Scope::SINGLETON, [], [], 'sample', 'lonely/pkg'),
        ];

        $this->generator->generate($classes, $this->tmpDir);

        $content = file_get_contents($this->tmpDir . '/sample.php');
        self::assertIsString($content);
        self::assertStringContainsString('Services:', $content);
        self::assertStringContainsString('return [', $content);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($dir);
    }
}
