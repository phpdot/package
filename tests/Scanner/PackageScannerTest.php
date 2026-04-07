<?php

declare(strict_types=1);

namespace PHPdot\Package\Tests\Scanner;

use PHPdot\Container\Scope;
use PHPdot\Package\Scanner\PackageScanner;
use PHPdot\Package\Tests\Fixtures\AbstractService;
use PHPdot\Package\Tests\Fixtures\NoAttributeClass;
use PHPdot\Package\Tests\Fixtures\SampleConfig;
use PHPdot\Package\Tests\Fixtures\SampleInterface;
use PHPdot\Package\Tests\Fixtures\SampleLoader;
use PHPdot\Package\Tests\Fixtures\SampleService;
use PHPdot\Package\Tests\Fixtures\SimpleService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PackageScannerTest extends TestCase
{
    private PackageScanner $scanner;
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->scanner = new PackageScanner();
        $this->fixturesDir = __DIR__ . '/../Fixtures';
    }

    #[Test]
    public function it_scans_singleton(): void
    {
        $results = $this->scanner->scanDirectory($this->fixturesDir, 'PHPdot\\Package\\Tests\\Fixtures\\', 'test/pkg');
        $found = $this->findByClass($results, SimpleService::class);

        self::assertNotNull($found);
        self::assertSame(Scope::SINGLETON, $found->scope);
    }

    #[Test]
    public function it_scans_scoped(): void
    {
        $results = $this->scanner->scanDirectory($this->fixturesDir, 'PHPdot\\Package\\Tests\\Fixtures\\', 'test/pkg');
        $found = $this->findByClass($results, SampleService::class);

        self::assertNotNull($found);
        self::assertSame(Scope::SCOPED, $found->scope);
    }

    #[Test]
    public function it_scans_config_as_singleton(): void
    {
        $results = $this->scanner->scanDirectory($this->fixturesDir, 'PHPdot\\Package\\Tests\\Fixtures\\', 'test/pkg');
        $found = $this->findByClass($results, SampleConfig::class);

        self::assertNotNull($found);
        self::assertSame('sample', $found->configName);
        self::assertSame(Scope::SINGLETON, $found->scope);
    }

    #[Test]
    public function it_scans_binds(): void
    {
        $results = $this->scanner->scanDirectory($this->fixturesDir, 'PHPdot\\Package\\Tests\\Fixtures\\', 'test/pkg');
        $found = $this->findByClass($results, SampleLoader::class);

        self::assertNotNull($found);
        self::assertContains(SampleInterface::class, $found->binds);
    }

    #[Test]
    public function it_reads_constructor_params(): void
    {
        $results = $this->scanner->scanDirectory($this->fixturesDir, 'PHPdot\\Package\\Tests\\Fixtures\\', 'test/pkg');
        $found = $this->findByClass($results, SampleService::class);

        self::assertNotNull($found);
        self::assertContains(SampleInterface::class, $found->params);
        self::assertContains(SampleConfig::class, $found->params);
    }

    #[Test]
    public function it_skips_builtin_types(): void
    {
        $results = $this->scanner->scanDirectory($this->fixturesDir, 'PHPdot\\Package\\Tests\\Fixtures\\', 'test/pkg');
        $found = $this->findByClass($results, SampleConfig::class);

        self::assertNotNull($found);
        self::assertSame([], $found->params);
    }

    #[Test]
    public function it_skips_classes_without_scope_attributes(): void
    {
        $results = $this->scanner->scanDirectory($this->fixturesDir, 'PHPdot\\Package\\Tests\\Fixtures\\', 'test/pkg');
        $found = $this->findByClass($results, NoAttributeClass::class);

        self::assertNull($found);
    }

    #[Test]
    public function it_skips_abstract_classes(): void
    {
        $results = $this->scanner->scanDirectory($this->fixturesDir, 'PHPdot\\Package\\Tests\\Fixtures\\', 'test/pkg');
        $found = $this->findByClass($results, AbstractService::class);

        self::assertNull($found);
    }

    #[Test]
    public function it_skips_interfaces(): void
    {
        $results = $this->scanner->scanDirectory($this->fixturesDir, 'PHPdot\\Package\\Tests\\Fixtures\\', 'test/pkg');
        $found = $this->findByClass($results, SampleInterface::class);

        self::assertNull($found);
    }

    #[Test]
    public function it_stores_package_name(): void
    {
        $results = $this->scanner->scanDirectory($this->fixturesDir, 'PHPdot\\Package\\Tests\\Fixtures\\', 'test/pkg');
        $found = $this->findByClass($results, SampleLoader::class);

        self::assertNotNull($found);
        self::assertSame('test/pkg', $found->package);
    }

    #[Test]
    public function it_returns_empty_for_missing_installed_json(): void
    {
        $results = $this->scanner->scan('/nonexistent/vendor');

        self::assertSame([], $results);
    }

    #[Test]
    public function it_returns_empty_for_empty_packages(): void
    {
        $tmpDir = sys_get_temp_dir() . '/phpdot_scanner_test_' . uniqid();
        mkdir($tmpDir . '/composer', 0755, true);
        file_put_contents($tmpDir . '/composer/installed.json', '{"packages": []}');

        $results = $this->scanner->scan($tmpDir);

        self::assertSame([], $results);

        unlink($tmpDir . '/composer/installed.json');
        rmdir($tmpDir . '/composer');
        rmdir($tmpDir);
    }

    /**
     * @param list<\PHPdot\Package\Scanner\ScannedClass> $results
     */
    private function findByClass(array $results, string $class): ?\PHPdot\Package\Scanner\ScannedClass
    {
        foreach ($results as $scanned) {
            if ($scanned->class === $class) {
                return $scanned;
            }
        }

        return null;
    }
}
