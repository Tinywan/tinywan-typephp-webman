<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

namespace TypePhp\Build;

final readonly class EmbeddedArchive
{
    /**
     * @param array<string, array{int, int}> $fileIndex
     * @param array<string, array{int, int}> $opcodeIndex
     */
    public function __construct(
        public string $path,
        public string $hash,
        public array $fileIndex,
        public array $opcodeIndex,
    ) {
    }

    public static function empty(string $path): self
    {
        return new self($path, '', [], []);
    }

    public function isEmpty(): bool
    {
        return $this->fileIndex === [] && $this->opcodeIndex === [];
    }
}
