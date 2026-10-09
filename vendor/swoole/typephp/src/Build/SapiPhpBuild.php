<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

namespace TypePhp\Build;

final readonly class SapiPhpBuild
{
    public function __construct(
        public string $sourceDirectory,
        public string $buildDirectory,
        public string $prefix,
        public string $phpxArchive,
        /** @var array<string, string> */
        public array $sapiArchives,
        public string $version,
        /** @var list<string> */
        public array $enabledExtensions = [],
    ) {
    }
}
