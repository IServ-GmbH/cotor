<?php

declare(strict_types=1);

namespace IServ\ComposerToolsInstaller\Tests\Domain\Config;

use IServ\ComposerToolsInstaller\Domain\Config\CotorConfig;
use IServ\ComposerToolsInstaller\Domain\Config\CotorConfigLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(CotorConfigLoader::class)]
#[CoversClass(CotorConfig::class)]
final class CotorConfigLoaderTest extends TestCase
{
    private string $tempDir;
    private Filesystem $filesystem;
    private CotorConfigLoader $loader;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cotor_tests_' . uniqid('', true);
        $this->filesystem = new Filesystem();
        $this->filesystem->mkdir($this->tempDir);
        $this->loader = new CotorConfigLoader($this->filesystem);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->tempDir);
    }

    public function testLoadNonExistentFileReturnsDefaultConfig(): void
    {
        $config = $this->loader->load($this->tempDir . DIRECTORY_SEPARATOR . 'non-existent.json');
        $this->assertNull($config->getPhp());
        $this->assertEmpty($config->getRepositories());
        $this->assertTrue($config->isSync());
    }

    public function testLoadValidFile(): void
    {
        $path = $this->tempDir . DIRECTORY_SEPARATOR . 'cotor.json';
        $this->filesystem->dumpFile($path, json_encode([
            'php' => '8.2.0',
            'repositories' => [['type' => 'composer', 'url' => 'https://example.com']],
            'sync' => false,
        ], JSON_THROW_ON_ERROR));

        $config = $this->loader->load($path);
        $this->assertEquals('8.2.0', $config->getPhp());
        $this->assertCount(1, $config->getRepositories());
        $this->assertEquals('https://example.com', $config->getRepositories()[0]['url']);
        $this->assertFalse($config->isSync());
    }

    public function testLoadInvalidJsonThrowsException(): void
    {
        $path = $this->tempDir . DIRECTORY_SEPARATOR . 'cotor.json';
        $this->filesystem->dumpFile($path, '{ invalid json }');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to parse config file');
        $this->loader->load($path);
    }

    public function testLoadInvalidSyncTypeThrowsException(): void
    {
        $path = $this->tempDir . DIRECTORY_SEPARATOR . 'cotor.json';
        $this->filesystem->dumpFile($path, json_encode(['sync' => 'not-a-bool'], JSON_THROW_ON_ERROR));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Field "sync" must be a boolean');
        $this->loader->load($path);
    }

    public function testLoadInvalidPhpTypeThrowsException(): void
    {
        $path = $this->tempDir . DIRECTORY_SEPARATOR . 'cotor.json';
        $this->filesystem->dumpFile($path, json_encode(['php' => 8.2], JSON_THROW_ON_ERROR));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Field "php" must be a string');
        $this->loader->load($path);
    }

    public function testLoadInvalidRepositoriesTypeThrowsException(): void
    {
        $path = $this->tempDir . DIRECTORY_SEPARATOR . 'cotor.json';
        $this->filesystem->dumpFile($path, json_encode(['repositories' => 'not-an-array'], JSON_THROW_ON_ERROR));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Field "repositories" must be an array');
        $this->loader->load($path);
    }
}
