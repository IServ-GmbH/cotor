<?php

declare(strict_types=1);

namespace IServ\ComposerToolsInstaller\Domain\Config;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Loads the cotor configuration from a file.
 */
final readonly class CotorConfigLoader
{
    public function __construct(
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    /**
     * @throws \RuntimeException
     */
    public function load(string $path): CotorConfig
    {
        if (!$this->filesystem->exists($path)) {
            return new CotorConfig();
        }

        $content = file_get_contents($path);
        if (false === $content) {
            throw new \RuntimeException(sprintf('Failed to read config file at %s', $path));
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException(sprintf('Failed to parse config file at %s: %s', $path, $e->getMessage()), 0, $e);
        }

        if (!is_array($data)) {
            throw new \RuntimeException(sprintf('Config file at %s must contain a JSON object.', $path));
        }

        $sync = true;
        if (isset($data['sync'])) {
            if (!is_bool($data['sync'])) {
                throw new \RuntimeException('Field "sync" must be a boolean.');
            }
            $sync = $data['sync'];
        }

        $php = null;
        if (isset($data['php'])) {
            if (!is_string($data['php'])) {
                throw new \RuntimeException('Field "php" must be a string.');
            }
            $php = $data['php'];
        }

        $repositories = [];
        if (isset($data['repositories'])) {
            if (!is_array($data['repositories'])) {
                throw new \RuntimeException('Field "repositories" must be an array.');
            }
            /** @var array<int, array<string, mixed>> $repositories */
            $repositories = $data['repositories'];
        }

        return new CotorConfig($php, $repositories, $sync);
    }
}
