<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

namespace TypePhp\Build;

final readonly class EmbeddedOpcodeBatch
{
    /**
     * @param array<string, string> $cachedBlobs
     * @param list<string> $pendingFiles
     * @param array<string, string> $cacheDirectories
     * @param array<string, true> $vendorFiles
     * @param array<string, true> $anonymousFiles
     * @param array<string, true> $skippedVendorFiles
     */
    public function __construct(
        private array $cachedBlobs,
        private array $pendingFiles,
        private array $cacheDirectories,
        private array $vendorFiles,
        private array $anonymousFiles,
        private array $skippedVendorFiles,
    ) {
    }

    /** @return array<string, string> */
    public function cachedBlobs(): array
    {
        return $this->cachedBlobs;
    }

    /** @return list<string> */
    public function pendingFiles(): array
    {
        return $this->pendingFiles;
    }

    public function pendingCount(): int
    {
        return count($this->pendingFiles);
    }

    public function cacheDirectory(string $file): string
    {
        return $this->cacheDirectories[$file]
            ?? throw new \LogicException("Missing OPcache directory for: {$file}");
    }

    public function isVendorFile(string $file): bool
    {
        return isset($this->vendorFiles[$file]);
    }

    public function isAnonymousFile(string $file): bool
    {
        return isset($this->anonymousFiles[$file]);
    }

    public function vendorHits(): int
    {
        return count(array_intersect_key($this->cachedBlobs, $this->vendorFiles));
    }

    public function anonymousHits(): int
    {
        return count(array_intersect_key($this->cachedBlobs, $this->anonymousFiles));
    }

    public function vendorCount(): int
    {
        return count($this->vendorFiles);
    }

    public function anonymousCount(): int
    {
        return count($this->anonymousFiles);
    }

    public function skippedVendorCount(): int
    {
        return count($this->skippedVendorFiles);
    }
}
