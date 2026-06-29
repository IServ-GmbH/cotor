<?php

declare(strict_types=1);

namespace IServ\ComposerToolsInstaller\Domain\Config;

use IServ\ComposerToolsInstaller\Domain\Composer;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Reads relevant configuration from the root composer.json.
 */
final readonly class RootComposerConfigReader
{
    public function __construct(
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function read(string $composerPath): CotorConfig
    {
        if (!$this->filesystem->exists($composerPath)) {
            return new CotorConfig(null, [], true);
        }

        $content = file_get_contents($composerPath);
        if (false === $content) {
            return new CotorConfig(null, [], true);
        }

        try {
            $composer = new Composer($content);
            $json = $composer->getJson();
        } catch (\JsonException) {
            return new CotorConfig(null, [], true);
        }

        /** @var mixed $php */
        $php = $json['require']['php'] ?? null;
        $php = is_string($php) ? $php : null;

        /** @var mixed $repositories */
        $repositories = $json['repositories'] ?? [];
        /** @var array<int, array<string, mixed>> $repositories */
        $repositories = is_array($repositories) ? $repositories : [];

        return new CotorConfig($php, $repositories, true);
    }
}
