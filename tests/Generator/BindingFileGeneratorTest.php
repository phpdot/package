<?php

declare(strict_types=1);

namespace PHPdot\Package\Tests\Generator;

use PHPdot\Container\Scope;
use PHPdot\Package\Generator\BindingFileGenerator;
use PHPdot\Package\Scanner\PackageMeta;
use PHPdot\Package\Scanner\ScannedClass;
use PHPdot\Package\Tests\Fixtures\SampleConfig;
use PHPdot\Package\Tests\Fixtures\SampleInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BindingFileGeneratorTest extends TestCase
{
    private BindingFileGenerator $generator;
    private string $tmpDir;

    /** @var array<string, PackageMeta> */
    private array $packages;

    protected function setUp(): void
    {
        $this->generator = new BindingFileGenerator();
        $this->tmpDir = sys_get_temp_dir() . '/phpdot_bindgen_' . uniqid();
        $this->packages = [
            'test/pkg' => new PackageMeta(
                name: 'test/pkg',
                description: 'A test package.',
                url: 'https://github.com/test/pkg',
                author: 'Test Author <test@example.com>',
            ),
        ];
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    #[Test]
    public function it_generates_binding_file_when_package_has_binds(): void
    {
        $classes = [
            new ScannedClass(
                'PHPdot\\Package\\Tests\\Fixtures\\SampleConfig',
                Scope::SINGLETON,
                [],
                [],
                'sample',
                'test/pkg',
            ),
            new ScannedClass(
                'PHPdot\\Package\\Tests\\Fixtures\\SampleLoader',
                Scope::SINGLETON,
                [SampleConfig::class],
                [SampleInterface::class],
                null,
                'test/pkg',
            ),
        ];

        $generated = $this->generator->generate($classes, $this->packages, $this->tmpDir);

        self::assertCount(1, $generated);
        self::assertFileExists($this->tmpDir . '/bindings/sample.php');
    }

    #[Test]
    public function it_generates_binding_file_when_service_has_unbound_interface_param(): void
    {
        $classes = [
            new ScannedClass(
                'PHPdot\\Package\\Tests\\Fixtures\\SampleService',
                Scope::SCOPED,
                [SampleInterface::class, SampleConfig::class],
                [],
                null,
                'test/pkg',
            ),
            new ScannedClass(
                'PHPdot\\Package\\Tests\\Fixtures\\SampleConfig',
                Scope::SINGLETON,
                [],
                [],
                'sample',
                'test/pkg',
            ),
        ];

        $generated = $this->generator->generate($classes, $this->packages, $this->tmpDir);

        self::assertCount(1, $generated);
    }

    #[Test]
    public function it_does_not_generate_when_no_bindings_or_interface_params(): void
    {
        $classes = [
            new ScannedClass(
                'App\\SimpleService',
                Scope::SINGLETON,
                [],
                [],
                null,
                'test/pkg',
            ),
        ];

        $generated = $this->generator->generate($classes, $this->packages, $this->tmpDir);

        self::assertSame([], $generated);
    }

    #[Test]
    public function it_has_professional_docblock_header(): void
    {
        $classes = $this->bindableClasses();

        $this->generator->generate($classes, $this->packages, $this->tmpDir);

        $content = $this->readGenerated('sample.php');
        self::assertStringContainsString('@package     test/pkg', $content);
        self::assertStringContainsString('@author      Test Author <test@example.com>', $content);
        self::assertStringContainsString('@see         https://github.com/test/pkg', $content);
        self::assertStringContainsString('@generated   phpdot/package', $content);
    }

    #[Test]
    public function it_includes_service_summary_table(): void
    {
        $classes = $this->bindableClasses();

        $this->generator->generate($classes, $this->packages, $this->tmpDir);

        $content = $this->readGenerated('sample.php');
        self::assertStringContainsString('Services registered by this package:', $content);
        self::assertStringContainsString('SampleConfig', $content);
        self::assertStringContainsString('SampleLoader', $content);
        self::assertStringContainsString('singleton', $content);
    }

    #[Test]
    public function it_includes_cli_commands(): void
    {
        $classes = $this->bindableClasses();

        $this->generator->generate($classes, $this->packages, $this->tmpDir);

        $content = $this->readGenerated('sample.php');
        self::assertStringContainsString('php dot bindings:show sample', $content);
        self::assertStringContainsString('php dot bindings:reset sample', $content);
    }

    #[Test]
    public function it_has_use_statements(): void
    {
        $classes = $this->bindableClasses();

        $this->generator->generate($classes, $this->packages, $this->tmpDir);

        $content = $this->readGenerated('sample.php');
        self::assertStringContainsString('use PHPdot\\Container\\ContainerBuilder;', $content);
        self::assertStringContainsString('use PHPdot\\Package\\Tests\\Fixtures\\SampleLoader;', $content);
        self::assertStringContainsString('use PHPdot\\Package\\Tests\\Fixtures\\SampleInterface;', $content);
    }

    #[Test]
    public function it_has_commented_override_section_for_binds(): void
    {
        $classes = $this->bindableClasses();

        $this->generator->generate($classes, $this->packages, $this->tmpDir);

        $content = $this->readGenerated('sample.php');
        self::assertStringContainsString('Override SampleInterface', $content);
        self::assertStringContainsString('Default: SampleInterface', $content);
        self::assertStringContainsString('SampleLoader', $content);
    }

    #[Test]
    public function it_has_commented_contextual_binding_section(): void
    {
        $classes = [
            new ScannedClass(
                'PHPdot\\Package\\Tests\\Fixtures\\SampleService',
                Scope::SCOPED,
                [SampleInterface::class, SampleConfig::class],
                [],
                null,
                'test/pkg',
            ),
            new ScannedClass(
                'PHPdot\\Package\\Tests\\Fixtures\\SampleConfig',
                Scope::SINGLETON,
                [],
                [],
                'sample',
                'test/pkg',
            ),
        ];

        $this->generator->generate($classes, $this->packages, $this->tmpDir);

        $content = $this->readGenerated('sample.php');
        self::assertStringContainsString('Contextual Bindings', $content);
        self::assertStringContainsString('->when(SampleService::class)', $content);
        self::assertStringContainsString('->needs(SampleInterface::class)', $content);
        self::assertStringContainsString('->provide(YourImplementation::class)', $content);
    }

    #[Test]
    public function it_uses_block_comments_for_code(): void
    {
        $classes = $this->bindableClasses();

        $this->generator->generate($classes, $this->packages, $this->tmpDir);

        $content = $this->readGenerated('sample.php');
        self::assertStringContainsString('/*', $content);
        self::assertStringContainsString('*/', $content);
    }

    #[Test]
    public function it_skips_existing_binding_files(): void
    {
        $bindingsDir = $this->tmpDir . '/bindings';
        mkdir($bindingsDir, 0o755, true);
        file_put_contents($bindingsDir . '/sample.php', '<?php return static function () {};');

        $generated = $this->generator->generate($this->bindableClasses(), $this->packages, $this->tmpDir);

        self::assertSame([], $generated);
    }

    #[Test]
    public function it_creates_bindings_directory(): void
    {
        $this->generator->generate($this->bindableClasses(), $this->packages, $this->tmpDir);

        self::assertDirectoryExists($this->tmpDir . '/bindings');
    }

    #[Test]
    public function it_generates_valid_php(): void
    {
        $this->generator->generate($this->bindableClasses(), $this->packages, $this->tmpDir);

        $content = $this->readGenerated('sample.php');
        $tokens = token_get_all($content);
        self::assertNotEmpty($tokens);
    }

    #[Test]
    public function it_returns_closure_accepting_container_builder(): void
    {
        $classes = $this->bindableClasses();

        $this->generator->generate($classes, $this->packages, $this->tmpDir);

        $content = $this->readGenerated('sample.php');
        self::assertStringContainsString('return static function (ContainerBuilder $builder): void {', $content);
    }

    #[Test]
    public function it_uses_package_name_as_binding_filename_when_no_config(): void
    {
        $classes = [
            new ScannedClass(
                'PHPdot\\Package\\Tests\\Fixtures\\SampleLoader',
                Scope::SINGLETON,
                [],
                [SampleInterface::class],
                null,
                'test/mylib',
            ),
        ];

        $packages = [
            'test/mylib' => new PackageMeta(name: 'test/mylib'),
        ];

        $this->generator->generate($classes, $packages, $this->tmpDir);

        self::assertFileExists($this->tmpDir . '/bindings/mylib.php');
    }

    /**
     * @return list<ScannedClass>
     */
    private function bindableClasses(): array
    {
        return [
            new ScannedClass(
                'PHPdot\\Package\\Tests\\Fixtures\\SampleConfig',
                Scope::SINGLETON,
                [],
                [],
                'sample',
                'test/pkg',
            ),
            new ScannedClass(
                'PHPdot\\Package\\Tests\\Fixtures\\SampleLoader',
                Scope::SINGLETON,
                [SampleConfig::class],
                [SampleInterface::class],
                null,
                'test/pkg',
            ),
        ];
    }

    private function readGenerated(string $filename): string
    {
        $content = file_get_contents($this->tmpDir . '/bindings/' . $filename);
        self::assertIsString($content);

        return $content;
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
