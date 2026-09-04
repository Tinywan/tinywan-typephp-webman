<?php

namespace TypePhp\Installer;

final class PhpBuildConfiguration
{
    /** @return list<string> */
    public static function parseShellWords(string $value): array
    {
        $words = [];
        $word = '';
        $quote = null;
        $wordStarted = false;
        $length = strlen($value);

        for ($index = 0; $index < $length; $index++) {
            $char = $value[$index];
            if ($quote === null) {
                if (str_contains(" \t\r\n\v\f", $char)) {
                    if ($wordStarted) {
                        $words[] = $word;
                        $word = '';
                        $wordStarted = false;
                    }
                    continue;
                }
                if ($char === "'" || $char === '"') {
                    $quote = $char;
                    $wordStarted = true;
                    continue;
                }
                if ($char === '\\') {
                    if (++$index >= $length) {
                        throw new \InvalidArgumentException('Incomplete escape sequence in configure options');
                    }
                    $word .= $value[$index];
                    $wordStarted = true;
                    continue;
                }
                $word .= $char;
                $wordStarted = true;
                continue;
            }

            if ($char === $quote) {
                $quote = null;
                continue;
            }
            if ($quote === '"' && $char === '\\' && $index + 1 < $length
                && str_contains('\"$`', $value[$index + 1])
            ) {
                $word .= $value[++$index];
                continue;
            }
            $word .= $char;
        }

        if ($quote !== null) {
            throw new \InvalidArgumentException('Unterminated quote in configure options');
        }
        if ($wordStarted) {
            $words[] = $word;
        }
        return $words;
    }

    /** @return list<string> */
    public static function parsePhpConfigOptions(string $value): array
    {
        $options = [];
        foreach (self::parseShellWords($value) as $option) {
            // php-config --configure-options loses the quoting of trailing build
            // assignments. Once the first assignment is reached, tokens that follow
            // may be either part of its value or another assignment, so none of them
            // can safely be reused as configure arguments.
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*=/', $option)) {
                break;
            }
            $options[] = $option;
        }
        return $options;
    }

    /**
     * @param string|list<string> $configureOptions
     * @return list<string>
     */
    public static function derive(string|array $configureOptions, string $prefix): array
    {
        $replace = [
            '--prefix', '--with-config-file-path', '--with-config-file-scan-dir',
            '--enable-embed', '--enable-cli', '--disable-cli', '--with-libdir',
        ];
        $drop = ['--with-apxs', '--with-apxs2', '--enable-fpm', '--with-fpm-systemd'];
        $result = [];
        $options = is_string($configureOptions) ? self::parseShellWords($configureOptions) : $configureOptions;
        foreach ($options as $option) {
            // Build assignments are not PHP feature configuration. Keep only long
            // configure options; callers can override CFLAGS through the environment.
            if (!str_starts_with($option, '--')) {
                continue;
            }
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
