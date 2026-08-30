<?php

namespace TypePhp\Installer;

final class PhpBuildConfiguration
{
    public static function parseShellWords(string $value): array
    {
        preg_match_all('/(?:[^\s"\']+|"[^"]*"|\'[^\']*\')+/', $value, $matches);
        return array_map(static function (string $word): string {
            if (strlen($word) >= 2 && (($word[0] === "'" && $word[-1] === "'") || ($word[0] === '"' && $word[-1] === '"'))) {
                return substr($word, 1, -1);
            }
            return $word;
        }, $matches[0]);
    }

    public static function derive(string $configureOptions, string $prefix): array
    {
        $replace = [
            '--prefix', '--with-config-file-path', '--with-config-file-scan-dir',
            '--enable-embed', '--enable-cli', '--disable-cli', '--with-libdir',
        ];
        $drop = ['--with-apxs', '--with-apxs2', '--enable-fpm', '--with-fpm-systemd'];
        $result = [];
        foreach (self::parseShellWords($configureOptions) as $option) {
            $name = explode('=', $option, 2)[0];
            if (in_array($name, $replace, true) || in_array($name, $drop, true)) {
                continue;
            }
            $result[] = $option;
        }
        return [
            '--prefix=' . $prefix,
            '--with-config-file-path=' . $prefix . '/lib',
            '--with-config-file-scan-dir=' . $prefix . '/lib/conf.d',
            '--enable-embed=shared',
            '--enable-cli',
            ...$result,
        ];
    }
}
