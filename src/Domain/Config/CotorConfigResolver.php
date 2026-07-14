<?php

declare(strict_types=1);

namespace IServ\ComposerToolsInstaller\Domain\Config;

/**
 * Resolves the merged configuration by merging different sources.
 */
final readonly class CotorConfigResolver
{
    /**
     * Resolves the merged configuration.
     *
     * Order of precedence:
     * 1. cotor.json (if values are set)
     * 2. Root composer.json (if sync is true)
     * 3. Defaults
     */
    public function resolve(CotorConfig $cotorConfig, CotorConfig $rootComposerConfig): CotorConfig
    {
        if (!$cotorConfig->isSync()) {
            return $cotorConfig;
        }

        $php = $cotorConfig->getPhp() ?? $rootComposerConfig->getPhp();

        // If cotorConfig has repositories, they overwrite the root ones.
        // Otherwise, use root repositories if sync is enabled.
        $repositories = $cotorConfig->getRepositories();
        if (empty($repositories)) {
            $repositories = $rootComposerConfig->getRepositories();
        }

        return new CotorConfig($php, $repositories, $cotorConfig->isSync());
    }
}
