<?php

declare(strict_types=1);

namespace IServ\ComposerToolsInstaller\Domain;

final class SemVer
{
    /** @var string */
    private $major;

    /** @var string */
    private $minor;

    /** @var string */
    private $patch;

    public function __construct(string $version)
    {
        if (!preg_match('/^v?(\d+)\.(\d+)\.(\d+)$/', $version, $matches)) {
            throw new \InvalidArgumentException(sprintf('Given string (%s) is no valid semver string!', $version));
        }

        [$this->major, $this->minor, $this->patch] = array_slice($matches, 1);
    }

    public function toMinorConstraint(): string
    {
        return sprintf('^%d.%d', $this->major, $this->minor);
    }

    public function toString(): string
    {
        return sprintf('%d.%d.%s', $this->major, $this->minor, $this->patch);
    }

    public function getMajor(): string
    {
        return $this->major;
    }

    public function getMinor(): string
    {
        return $this->minor;
    }

    public function getPatch(): string
    {
        return $this->patch;
    }
}
