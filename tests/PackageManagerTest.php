<?php

declare(strict_types=1);

namespace PHPdot\Package\Tests;

use PHPdot\Package\Manifest;
use PHPdot\Package\PackageManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PackageManagerTest extends TestCase
{
    private string $vendorDir;
    private string $configDir;
    private string $containerDir;

    protected function setUp(): void
    {
        $this->vendorDir = sys_get_temp_dir() . '/phpdot_mgr_' . uniqid();
        $this->configDir = sys_get_temp_dir() . '/phpdot_cfg_' . uniqid();
        $this->containerDir = sys_get_temp_dir() . '/phpdot_ctr_' . uniqid();

        mkdir($this->vendorDir . '/composer', 0o755, true);
        file_put_contents(
            $this->vendorDir . '/composer/installed.json',
            '{"packages": []}',
        );
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->vendorDir);
        $this->removeDir($this->configDir);
        $this->removeDir($this->containerDir);
    }

    #[Test]
    public function it_creates_phpdot_directory(): void
    {
        $manager = new PackageManager($this->vendorDir, $this->configDir, $this->containerDir);
        $manager->rebuild();

        self::assertDirectoryExists($this->vendorDir . '/phpdot');
    }

    #[Test]
    public function it_writes_definitions_file(): void
    {
        $manager = new PackageManager($this->vendorDir, $this->configDir, $this->containerDir);
        $manager->rebuild();

        self::assertFileExists($manager->definitionsPath());

        $content = file_get_contents($manager->definitionsPath());
        self::assertIsString($content);
        self::assertStringContainsString('return [', $content);
    }

    #[Test]
    public function it_writes_manifest_file(): void
    {
        $manager = new PackageManager($this->vendorDir, $this->configDir, $this->containerDir);
        $manager->rebuild();

        self::assertFileExists($manager->manifestPath());

        $content = file_get_contents($manager->manifestPath());
        self::assertIsString($content);
        self::assertStringContainsString('generated_at', $content);
    }

    #[Test]
    public function it_returns_correct_counts(): void
    {
        $manager = new PackageManager($this->vendorDir, $this->configDir, $this->containerDir);
        $result = $manager->rebuild();

        self::assertSame(0, $result->packageCount);
        self::assertSame(0, $result->serviceCount);
        self::assertSame(0, $result->bindingCount);
        self::assertSame(0, $result->configCount);
        self::assertSame([], $result->generatedConfigs);
        self::assertSame([], $result->generatedBindings);
    }

    #[Test]
    public function it_clears_definitions_and_manifest(): void
    {
        $manager = new PackageManager($this->vendorDir, $this->configDir, $this->containerDir);
        $manager->rebuild();

        self::assertFileExists($manager->definitionsPath());
        self::assertFileExists($manager->manifestPath());

        $manager->clear();

        self::assertFileDoesNotExist($manager->definitionsPath());
        self::assertFileDoesNotExist($manager->manifestPath());
    }

    #[Test]
    public function it_clear_is_silent_when_no_files(): void
    {
        $manager = new PackageManager($this->vendorDir, $this->configDir, $this->containerDir);
        $manager->clear();

        self::assertFileDoesNotExist($manager->definitionsPath());
    }

    #[Test]
    public function it_returns_null_manifest_when_not_built(): void
    {
        $manager = new PackageManager($this->vendorDir, $this->configDir, $this->containerDir);

        self::assertNull($manager->manifest());
    }

    #[Test]
    public function it_returns_manifest_after_rebuild(): void
    {
        $manager = new PackageManager($this->vendorDir, $this->configDir, $this->containerDir);
        $manager->rebuild();

        $manifest = $manager->manifest();

        self::assertInstanceOf(Manifest::class, $manifest);
        self::assertSame([], $manifest->packageNames());
    }

    #[Test]
    public function it_returns_correct_paths(): void
    {
        $manager = new PackageManager($this->vendorDir, $this->configDir, $this->containerDir);

        self::assertSame($this->vendorDir . '/phpdot/definitions.php', $manager->definitionsPath());
        self::assertSame($this->vendorDir . '/phpdot/manifest.php', $manager->manifestPath());
    }

    #[Test]
    public function it_accepts_container_path(): void
    {
        $manager = new PackageManager($this->vendorDir, $this->configDir, $this->containerDir);
        $result = $manager->rebuild();

        self::assertSame([], $result->generatedBindings);
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
