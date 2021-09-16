<?php

declare(strict_types=1);

namespace IServ\ComposerToolsInstaller\Domain;

final class Package
{
    /** @var string */
    private $vendor;

    /** @var string */
    private $name;

    /** @var string */
    private $version;

    public function __construct(string $vendor, string $name, string $version = '*')
    {
        $this->vendor = $vendor;
        $this->name = $name;
        $this->version = $version;
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
