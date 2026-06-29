<?php

declare(strict_types=1);

namespace IServ\ComposerToolsInstaller\Tests\Domain\Config;

use IServ\ComposerToolsInstaller\Domain\Composer;
use IServ\ComposerToolsInstaller\Domain\Config\CotorConfig;
use IServ\ComposerToolsInstaller\Domain\Config\RootComposerConfigReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(RootComposerConfigReader::class)]
#[UsesClass(Composer::class)]
#[UsesClass(CotorConfig::class)]
final class RootComposerConfigReaderTest extends TestCase
{
    private string $tempDir;
    private Filesystem $filesystem;
    private RootComposerConfigReader $reader;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cotor_tests_' . uniqid('', true);
        $this->filesystem = new Filesystem();
        $this->filesystem->mkdir($this->tempDir);
        $this->reader = new RootComposerConfigReader($this->filesystem);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->tempDir);
    }

    public function testReadNonExistentFileReturnsDefaultConfig(): void
    {
        $config = $this->reader->read($this->tempDir . DIRECTORY_SEPARATOR . 'non-existent.json');
        $this->assertNull($config->getPhp());
        $this->assertEmpty($config->getRepositories());
        $this->assertTrue($config->isSync());
    }

    public function testReadValidComposerJson(): void
    {
        $path = $this->tempDir . DIRECTORY_SEPARATOR . 'composer.json';
        $this->filesystem->dumpFile($path, json_encode([
            'require' => [
                'php' => '>=8.1'
            ],
            'repositories' => [
                ['type' => 'composer', 'url' => 'https://repo.example.com']
            ]
        ], JSON_THROW_ON_ERROR));

        $config = $this->reader->read($path);
        $this->assertEquals('>=8.1', $config->getPhp());
        $this->assertCount(1, $config->getRepositories());
        $this->assertEquals('https://repo.example.com', $config->getRepositories()[0]['url']);
    }

    public function testReadWithCaretConstraint(): void
    {
        $path = $this->tempDir . DIRECTORY_SEPARATOR . 'composer.json';
        $this->filesystem->dumpFile($path, json_encode([
            'require' => ['php' => '^8.2']
        ], JSON_THROW_ON_ERROR));

        $config = $this->reader->read($path);
        $this->assertEquals('^8.2', $config->getPhp());
    }

    public function testReadWithFullVersion(): void
    {
        $path = $this->tempDir . DIRECTORY_SEPARATOR . 'composer.json';
        $this->filesystem->dumpFile($path, json_encode([
            'require' => ['php' => '8.3.1']
        ], JSON_THROW_ON_ERROR));

        $config = $this->reader->read($path);
        $this->assertEquals('8.3.1', $config->getPhp());
    }
}
