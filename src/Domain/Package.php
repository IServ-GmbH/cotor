<?php

declare(strict_types=1);

namespace IServ\ComposerToolsInstaller\Domain;

final class Package
{
    public const DEFAULT_VERSION = '*';

    /** @var string */
    private $vendor;

    /** @var string */
    private $name;

    /** @var string */
    private $version;

    public function __construct(string $vendor, string $name, string $version = self::DEFAULT_VERSION)
    {
        $this->vendor = $vendor;
        $this->name = $name;
        $this->version = $version;
    }

    public static function createFromComposerName(string $vendorName, string $version = self::DEFAULT_VERSION): self
    {
        if (substr_count($vendorName, '/') !== 1) {
            throw new \InvalidArgumentException(sprintf('%s is not valid composer package name!', $vendorName));
        }

        [$vendor, $name] = explode('/', $vendorName, 2);

        return new self($vendor, $name, $version);
    }

    public function withVersion(string $version): self
    {
        $self = clone $this;

        $self->version = $version;

        return $self;
    }

    public function getComposerName(): string
    {
        return $this->vendor . '/' . $this->name;
    }

    public function getVendor(): string
    {
        return $this->vendor;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getVersion(): string
    {
        return $this->version;
    }
}
