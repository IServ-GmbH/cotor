<?php

declare(strict_types=1);

namespace IServ\ComposerToolsInstaller\Domain;

final class Composer
{
    /** @var int */
    private $indent;

    /** @var array<string, mixed> */
    private $json;

    /**
     * @throws \JsonException
     */
    public function __construct(string $content)
    {
        /** @var array<string, mixed> $json */
        $json = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->json = $json;
        $this->indent = preg_match('/^\s\s\s\s"name"/', $content) ? 4 : 2;
    }

    /**
     * @throws \JsonException
     */
    public function toPrettyJsonString(): string
    {
        $composerContent = json_encode($this->json, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (2 === $this->indent) {
            $composerContent = preg_replace('/^(  +?)\\1(?=[^ ])/m', '$1', $composerContent);
        }

        $composerContent .= "\n";

        return $composerContent;
    }

    /** @return array<string, mixed> */
    public function getJson(): array
    {
        return $this->json;
    }

    /** @param array<string, mixed> $json */
    public function setJson(array $json): void
    {
        $this->json = $json;
    }
}
