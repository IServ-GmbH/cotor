<?php

declare(strict_types=1);

namespace IServ\ComposerToolsInstaller\Tests\Domain\Config;

use IServ\ComposerToolsInstaller\Domain\Config\CotorConfig;
use IServ\ComposerToolsInstaller\Domain\Config\CotorConfigResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CotorConfigResolver::class)]
#[CoversClass(CotorConfig::class)]
final class CotorConfigResolverTest extends TestCase
{
    private CotorConfigResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new CotorConfigResolver();
    }

    public function testResolveWithSyncFalse(): void
    {
        $cotorConfig = new CotorConfig('8.2.0', [], false);
        $rootConfig = new CotorConfig('8.1.0', [['type' => 'composer', 'url' => 'https://root.com']], true);

        $resolved = $this->resolver->resolve($cotorConfig, $rootConfig);

        $this->assertEquals('8.2.0', $resolved->getPhp());
        $this->assertEmpty($resolved->getRepositories());
        $this->assertFalse($resolved->isSync());
    }

    public function testResolveWithSyncTrueAndCotorOverrides(): void
    {
        $cotorConfig = new CotorConfig('8.2.0', [['type' => 'composer', 'url' => 'https://cotor.com']], true);
        $rootConfig = new CotorConfig('8.1.0', [['type' => 'composer', 'url' => 'https://root.com']], true);

        $resolved = $this->resolver->resolve($cotorConfig, $rootConfig);

        $this->assertEquals('8.2.0', $resolved->getPhp());
        $this->assertCount(1, $resolved->getRepositories());
        $this->assertEquals('https://cotor.com', $resolved->getRepositories()[0]['url']);
    }

    public function testResolveWithSyncTrueAndCotorPartial(): void
    {
        $cotorConfig = new CotorConfig('8.2.0', [], true);
        $rootConfig = new CotorConfig('8.1.0', [['type' => 'composer', 'url' => 'https://root.com']], true);

        $resolved = $this->resolver->resolve($cotorConfig, $rootConfig);

        $this->assertEquals('8.2.0', $resolved->getPhp());
        $this->assertCount(1, $resolved->getRepositories());
        $this->assertEquals('https://root.com', $resolved->getRepositories()[0]['url']);
    }

    public function testResolveWithSyncTrueAndRootValuesOnly(): void
    {
        $cotorConfig = new CotorConfig(null, [], true);
        $rootConfig = new CotorConfig('8.1.0', [['type' => 'composer', 'url' => 'https://root.com']], true);

        $resolved = $this->resolver->resolve($cotorConfig, $rootConfig);

        $this->assertEquals('8.1.0', $resolved->getPhp());
        $this->assertCount(1, $resolved->getRepositories());
        $this->assertEquals('https://root.com', $resolved->getRepositories()[0]['url']);
    }
}
