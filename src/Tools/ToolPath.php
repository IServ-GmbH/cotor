<?php

declare(strict_types=1);

namespace IServ\ComposerToolsInstaller\Tools;

final class ToolPath
{
    public static function create(string $toolsDir, string $name, ?string $file = null): string
    {
        if (null === $file) {
            return sprintf('%s/.%s', $toolsDir, $name);
        }

        return sprintf('%s/.%s/%s', $toolsDir, $name, $file);
    }

    public static function createExecutable(string $toolsDir, string $name): string
    {
        return sprintf('%s/%s', $toolsDir, $name);
    }

    /** @return array<int, string> */
    public static function glob(string $toolsDir): array
    {
        $targetDirs = glob($toolsDir . '/.*', GLOB_ONLYDIR);

        return array_filter($targetDirs, static fn (string $dir): bool => !str_ends_with($dir, '/.') && !str_ends_with($dir, '/..'));
    }

    public static function path2name(string $tooldir): string
    {
        return substr(strrchr($tooldir, '/'), 2); // 2 = Remove slash and dot
    }
}
