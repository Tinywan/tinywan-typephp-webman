<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

namespace TypePhp\Build;

final class EmbeddedArchiveBuilder
{
    /**
     * @param list<string> $files
     * @param array<string, string> $opcodeBlobs source file => blob file
     * @param array<string, string> $opcodeKeys source file => runtime lookup key
     */
    public function build(
        string $archivePath,
        array $files,
        array $opcodeBlobs,
        array $opcodeKeys = [],
    ): EmbeddedArchive {
        $this->ensureDirectory(dirname($archivePath));
        $temporaryPath = $archivePath . '.tmp';
        $stream = fopen($temporaryPath, 'wb');
        if ($stream === false) {
            throw new \RuntimeException("Cannot create embedded file archive: {$temporaryPath}");
        }

        $fileIndex = [];
        $opcodeIndex = [];
        try {
            foreach ($files as $file) {
                $fileIndex[$file] = $this->append($stream, $file, 'embedded file');
            }
            foreach ($opcodeBlobs as $sourceFile => $blobFile) {
                $key = $opcodeKeys[$sourceFile] ?? $sourceFile;
                $opcodeIndex[$key] = $this->append($stream, $blobFile, 'opcode blob');
            }
        } catch (\Throwable $error) {
            fclose($stream);
            @unlink($temporaryPath);
            throw $error;
        }

        if (!fclose($stream)) {
            @unlink($temporaryPath);
            throw new \RuntimeException("Cannot close embedded file archive: {$temporaryPath}");
        }

        $temporaryHash = hash_file('sha256', $temporaryPath);
        if (!is_string($temporaryHash)) {
            @unlink($temporaryPath);
            throw new \RuntimeException("Cannot hash embedded file archive: {$temporaryPath}");
        }
        $currentHash = is_file($archivePath) ? hash_file('sha256', $archivePath) : false;
        if ($currentHash === $temporaryHash) {
            if (!unlink($temporaryPath)) {
                throw new \RuntimeException("Cannot remove unchanged embedded archive: {$temporaryPath}");
            }
        } else {
            try {
                AtomicFile::replace($temporaryPath, $archivePath);
            } finally {
                if (is_file($temporaryPath)) {
                    @unlink($temporaryPath);
                }
            }
        }

        return new EmbeddedArchive($archivePath, $temporaryHash, $fileIndex, $opcodeIndex);
    }

    /** @return array{int, int} */
    private function append(mixed $output, string $inputPath, string $description): array
    {
        $offset = ftell($output);
        if (!is_int($offset)) {
            throw new \RuntimeException('Cannot determine embedded archive offset');
        }
        $input = fopen($inputPath, 'rb');
        if ($input === false) {
            throw new \RuntimeException("Cannot read {$description}: {$inputPath}");
        }
        try {
            $length = stream_copy_to_stream($input, $output);
        } finally {
            fclose($input);
        }
        if (!is_int($length)) {
            throw new \RuntimeException("Cannot copy {$description}: {$inputPath}");
        }
        return [$offset, $length];
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException("Cannot create embedded archive directory: {$directory}");
        }
    }
}
