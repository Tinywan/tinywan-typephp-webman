<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

namespace TypePhp\Build;

final readonly class ProjectConversion
{
    /**
     * @param list<string> $files
     * @param list<string> $sourceFiles
     */
    public function __construct(
        private array $files,
        private array $sourceFiles,
        private int $validSourceCount,
    ) {
    }

    /** @return list<string> */
    public function files(): array
    {
        return $this->files;
    }

    /** @return list<string> */
    public function sourceFiles(): array
    {
        return $this->sourceFiles;
    }

    public function hasValidSources(): bool
    {
        return $this->validSourceCount !== 0;
    }
}
