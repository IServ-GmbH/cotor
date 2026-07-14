<?php

declare(strict_types=1);

namespace IServ\ComposerToolsInstaller\Domain\Config;

/**
 * Represents the merged configuration for cotor.
 */
final readonly class CotorConfig
{
    /**
     * @param array<int, array<string, mixed>> $repositories
     */
    public function __construct(
        private ?string $php = null,
        private array $repositories = [],
        private bool $sync = true,
    ) {
    }

    public function getPhp(): ?string
    {
        return $this->php;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRepositories(): array
    {
        return $this->repositories;
    }

    public function isSync(): bool
    {
        return $this->sync;
    }
}
