<?php

final class AtaBlockDevice
{
    public function available(): bool
    {
        return kernel_disk_available();
    }

    public function readSector(int $lba): string
    {
        return kernel_disk_read_sector($lba);
    }

    public function writeSector(int $lba, string $data): bool
    {
        return kernel_disk_write_sector($lba, $data);
    }

    public function flush(): bool
    {
        return kernel_disk_flush();
    }

    public function cacheHits(): int
    {
        return kernel_disk_cache_hits();
    }

    public function cacheMisses(): int
    {
        return kernel_disk_cache_misses();
    }
}

/*
 * Deliberately small FAT16 implementation. The ATA driver only transports
 * sectors; filesystem layout, allocation, directory entries, and file data
 * are implemented in TypePHP so this code can later be shared with hosted
 * and in-memory block devices.
 */
final class Fat16Volume
{
    private AtaBlockDevice $device;
    private int $bytesPerSector = 0;
    private int $sectorsPerCluster = 0;
    private int $reservedSectors = 0;
    private int $fatCount = 0;
    private int $rootEntryCount = 0;
    private int $sectorsPerFat = 0;
    private int $totalSectors = 0;
    private int $firstFatSector = 0;
    private int $firstRootSector = 0;
    private int $rootSectorCount = 0;
    private int $firstDataSector = 0;
    private int $clusterCount = 0;
    private string $error = '';

    public function __construct(AtaBlockDevice $device)
    {
        $this->device = $device;
    }

    private function quotient(int $value, int $divisor): int
    {
        return (int) (($value - ($value % $divisor)) / $divisor);
    }

    private function ceilQuotient(int $value, int $divisor): int
    {
        return $this->quotient($value + $divisor - 1, $divisor);
    }

    private function byteAt(string $data, int $offset): int
    {
        return ord($data[$offset]);
    }

    private function readU16(string $data, int $offset): int
    {
        return $this->byteAt($data, $offset)
            + ($this->byteAt($data, $offset + 1) << 8);
    }

    private function readU32(string $data, int $offset): int
    {
        return $this->byteAt($data, $offset)
            + ($this->byteAt($data, $offset + 1) << 8)
            + ($this->byteAt($data, $offset + 2) << 16)
            + ($this->byteAt($data, $offset + 3) << 24);
    }

    private function writeU16(string &$data, int $offset, int $value): void
    {
        $data[$offset] = chr($value & 0xff);
        $data[$offset + 1] = chr(($value >> 8) & 0xff);
    }

    private function writeU32(string &$data, int $offset, int $value): void
    {
        $data[$offset] = chr($value & 0xff);
        $data[$offset + 1] = chr(($value >> 8) & 0xff);
        $data[$offset + 2] = chr(($value >> 16) & 0xff);
        $data[$offset + 3] = chr(($value >> 24) & 0xff);
    }

    private function blankSector(): string
    {
        $result = '';
        for ($index = 0; $index < 512; $index++) {
            $result .= chr(0);
        }
        return $result;
    }

    private function sectorData(string $data): string
    {
        $result = $data;
        while (strlen($result) < 512) {
            $result .= chr(0);
        }
        return $result;
    }

    public function lastError(): string
    {
        return $this->error;
    }

    public function mount(): bool
    {
        $boot = $this->device->readSector(0);
        if (strlen($boot) !== 512) {
            $this->error = 'unable to read FAT boot sector';
            return false;
        }
        if ($this->byteAt($boot, 510) !== 0x55 || $this->byteAt($boot, 511) !== 0xaa) {
            $this->error = 'invalid FAT boot signature';
            return false;
        }

        $this->bytesPerSector = $this->readU16($boot, 11);
        $this->sectorsPerCluster = $this->byteAt($boot, 13);
        $this->reservedSectors = $this->readU16($boot, 14);
        $this->fatCount = $this->byteAt($boot, 16);
        $this->rootEntryCount = $this->readU16($boot, 17);
        $this->totalSectors = $this->readU16($boot, 19);
        if ($this->totalSectors === 0) {
            $this->totalSectors = $this->readU32($boot, 32);
        }
        $this->sectorsPerFat = $this->readU16($boot, 22);

        if ($this->bytesPerSector !== 512 || $this->sectorsPerCluster <= 0
            || $this->reservedSectors <= 0 || $this->fatCount <= 0
            || $this->rootEntryCount <= 0 || $this->sectorsPerFat <= 0) {
            $this->error = 'unsupported FAT16 geometry';
            return false;
        }

        $this->rootSectorCount = $this->ceilQuotient($this->rootEntryCount * 32, 512);
        $this->firstFatSector = $this->reservedSectors;
        $this->firstRootSector = $this->firstFatSector + $this->fatCount * $this->sectorsPerFat;
        $this->firstDataSector = $this->firstRootSector + $this->rootSectorCount;
        $this->clusterCount = $this->quotient(
            $this->totalSectors - $this->firstDataSector,
            $this->sectorsPerCluster
        );
        if ($this->clusterCount < 4085 || $this->clusterCount >= 65525) {
            $this->error = 'disk is not FAT16';
            return false;
        }
        $this->error = '';
        return true;
    }

    private function validNameCharacter(int $character): bool
    {
        return ($character >= 65 && $character <= 90)
            || ($character >= 48 && $character <= 57)
            || $character === 45 || $character === 95;
    }

    private function fatName(string $name): string
    {
        $upper = strtoupper($name);
        $base = '';
        $extension = '';
        $inExtension = false;
        for ($index = 0; $index < strlen($upper); $index++) {
            $character = ord($upper[$index]);
            if ($character === 46 && !$inExtension) {
                $inExtension = true;
                continue;
            }
            if (!$this->validNameCharacter($character)) {
                return '';
            }
            if ($inExtension) {
                if (strlen($extension) >= 3) {
                    return '';
                }
                $extension .= $upper[$index];
            } else {
                if (strlen($base) >= 8) {
                    return '';
                }
                $base .= $upper[$index];
            }
        }
        if (strlen($base) === 0) {
            return '';
        }
        while (strlen($base) < 8) {
            $base .= ' ';
        }
        while (strlen($extension) < 3) {
            $extension .= ' ';
        }
        return $base . $extension;
    }

    private function displayName(string $entry): string
    {
        $base = rtrim(substr($entry, 0, 8));
        $extension = rtrim(substr($entry, 8, 3));
        return strlen($extension) === 0 ? $base : $base . '.' . $extension;
    }

    /* A directory location is encoded as sector * 512 + byte offset. */
    private function rootEntryLocation(string $fatName): int
    {
        for ($sectorIndex = 0; $sectorIndex < $this->rootSectorCount; $sectorIndex++) {
            $sectorNumber = $this->firstRootSector + $sectorIndex;
            $sector = $this->device->readSector($sectorNumber);
            if (strlen($sector) !== 512) {
                return -1;
            }
            for ($offset = 0; $offset < 512; $offset += 32) {
                $first = $this->byteAt($sector, $offset);
                if ($first === 0x00) {
                    return -1;
                }
                if ($first !== 0xe5 && substr($sector, $offset, 11) === $fatName) {
                    return $sectorNumber * 512 + $offset;
                }
            }
        }
        return -1;
    }

    private function directoryEntryLocation(int $directoryCluster, string $fatName): int
    {
        if ($directoryCluster === 0) {
            return $this->rootEntryLocation($fatName);
        }
        $cluster = $directoryCluster;
        $visited = 0;
        while ($cluster >= 2 && $cluster < 0xfff8 && $visited < $this->clusterCount) {
            $firstSector = $this->clusterSector($cluster);
            for ($sectorIndex = 0; $sectorIndex < $this->sectorsPerCluster; $sectorIndex++) {
                $sectorNumber = $firstSector + $sectorIndex;
                $sector = $this->device->readSector($sectorNumber);
                if (strlen($sector) !== 512) {
                    return -1;
                }
                for ($offset = 0; $offset < 512; $offset += 32) {
                    $first = $this->byteAt($sector, $offset);
                    if ($first === 0x00) {
                        return -1;
                    }
                    if ($first !== 0xe5 && substr($sector, $offset, 11) === $fatName) {
                        return $sectorNumber * 512 + $offset;
                    }
                }
            }
            $cluster = $this->readFat($cluster);
            $visited++;
        }
        return -1;
    }

    private function pathEntryLocation(string $path): int
    {
        $length = strlen($path);
        $index = 0;
        $directoryCluster = 0;
        while ($index < $length && $path[$index] === '/') {
            $index++;
        }
        if ($index === $length) {
            return -1;
        }
        while ($index < $length) {
            $start = $index;
            while ($index < $length && $path[$index] !== '/') {
                $index++;
            }
            $name = substr($path, $start, $index - $start);
            while ($index < $length && $path[$index] === '/') {
                $index++;
            }
            $fatName = $this->fatName($name);
            if (strlen($fatName) !== 11) {
                return -1;
            }
            $location = $this->directoryEntryLocation($directoryCluster, $fatName);
            if ($location < 0 || $index === $length) {
                return $location;
            }
            $entry = $this->readEntry($location);
            if (($this->byteAt($entry, 11) & 0x10) === 0) {
                return -1;
            }
            $directoryCluster = $this->readU16($entry, 26);
        }
        return -1;
    }

    private function pathDirectoryCluster(string $path): int
    {
        if ($path === '' || $path === '/' || $path === '.') {
            return 0;
        }
        $location = $this->pathEntryLocation($path);
        if ($location < 0) {
            return -1;
        }
        $entry = $this->readEntry($location);
        return ($this->byteAt($entry, 11) & 0x10) !== 0
            ? $this->readU16($entry, 26)
            : -1;
    }

    private function pathWithoutTrailingSlash(string $path): string
    {
        $result = $path;
        while (strlen($result) > 1 && $result[strlen($result) - 1] === '/') {
            $result = substr($result, 0, strlen($result) - 1);
        }
        return $result;
    }

    private function pathLeafName(string $path): string
    {
        $clean = $this->pathWithoutTrailingSlash($path);
        $lastSlash = -1;
        for ($index = 0; $index < strlen($clean); $index++) {
            if ($clean[$index] === '/') {
                $lastSlash = $index;
            }
        }
        return substr($clean, $lastSlash + 1);
    }

    private function pathParentCluster(string $path): int
    {
        $clean = $this->pathWithoutTrailingSlash($path);
        $lastSlash = -1;
        for ($index = 0; $index < strlen($clean); $index++) {
            if ($clean[$index] === '/') {
                $lastSlash = $index;
            }
        }
        if ($lastSlash <= 0) {
            return 0;
        }
        return $this->pathDirectoryCluster(substr($clean, 0, $lastSlash));
    }

    private function freeRootEntryLocation(): int
    {
        for ($sectorIndex = 0; $sectorIndex < $this->rootSectorCount; $sectorIndex++) {
            $sectorNumber = $this->firstRootSector + $sectorIndex;
            $sector = $this->device->readSector($sectorNumber);
            if (strlen($sector) !== 512) {
                return -1;
            }
            for ($offset = 0; $offset < 512; $offset += 32) {
                $first = $this->byteAt($sector, $offset);
                if ($first === 0x00 || $first === 0xe5) {
                    return $sectorNumber * 512 + $offset;
                }
            }
        }
        return -1;
    }

    private function freeDirectoryEntryLocation(int $directoryCluster): int
    {
        if ($directoryCluster === 0) {
            return $this->freeRootEntryLocation();
        }
        $cluster = $directoryCluster;
        $lastCluster = -1;
        $visited = 0;
        while ($cluster >= 2 && $cluster < 0xfff8 && $visited < $this->clusterCount) {
            $lastCluster = $cluster;
            $firstSector = $this->clusterSector($cluster);
            for ($sectorIndex = 0; $sectorIndex < $this->sectorsPerCluster; $sectorIndex++) {
                $sectorNumber = $firstSector + $sectorIndex;
                $sector = $this->device->readSector($sectorNumber);
                if (strlen($sector) !== 512) {
                    return -1;
                }
                for ($offset = 0; $offset < 512; $offset += 32) {
                    $first = $this->byteAt($sector, $offset);
                    if ($first === 0x00 || $first === 0xe5) {
                        return $sectorNumber * 512 + $offset;
                    }
                }
            }
            $cluster = $this->readFat($cluster);
            $visited++;
        }
        if ($lastCluster < 2) {
            return -1;
        }
        $newCluster = $this->findFreeCluster(2);
        if ($newCluster < 0 || !$this->writeFat($newCluster, 0xffff)) {
            return -1;
        }
        if (!$this->clearCluster($newCluster)
            || !$this->writeFat($lastCluster, $newCluster)) {
            $this->writeFat($newCluster, 0);
            return -1;
        }
        return $this->clusterSector($newCluster) * 512;
    }

    private function readEntry(int $location): string
    {
        $sectorNumber = $this->quotient($location, 512);
        $offset = $location % 512;
        return substr($this->device->readSector($sectorNumber), $offset, 32);
    }

    private function writeEntry(int $location, string $fatName, int $attributes, int $cluster, int $size): bool
    {
        $sectorNumber = $this->quotient($location, 512);
        $offset = $location % 512;
        $sector = $this->device->readSector($sectorNumber);
        if (strlen($sector) !== 512) {
            return false;
        }
        for ($index = 0; $index < 32; $index++) {
            $sector[$offset + $index] = chr(0);
        }
        for ($index = 0; $index < 11; $index++) {
            $sector[$offset + $index] = $fatName[$index];
        }
        $sector[$offset + 11] = chr($attributes);
        $this->writeU16($sector, $offset + 26, $cluster);
        $this->writeU32($sector, $offset + 28, $size);
        return $this->device->writeSector($sectorNumber, $sector);
    }

    private function clusterSector(int $cluster): int
    {
        return $this->firstDataSector + ($cluster - 2) * $this->sectorsPerCluster;
    }

    private function readFat(int $cluster): int
    {
        $fatOffset = $cluster * 2;
        $sectorNumber = $this->firstFatSector + $this->quotient($fatOffset, 512);
        $sector = $this->device->readSector($sectorNumber);
        if (strlen($sector) !== 512) {
            return 0xffff;
        }
        return $this->readU16($sector, $fatOffset % 512);
    }

    private function writeFat(int $cluster, int $value): bool
    {
        $fatOffset = $cluster * 2;
        $relativeSector = $this->quotient($fatOffset, 512);
        $offset = $fatOffset % 512;
        for ($fat = 0; $fat < $this->fatCount; $fat++) {
            $sectorNumber = $this->firstFatSector + $fat * $this->sectorsPerFat + $relativeSector;
            $sector = $this->device->readSector($sectorNumber);
            if (strlen($sector) !== 512) {
                return false;
            }
            $this->writeU16($sector, $offset, $value);
            if (!$this->device->writeSector($sectorNumber, $sector)) {
                return false;
            }
        }
        return true;
    }

    private function freeChain(int $cluster): bool
    {
        $visited = 0;
        while ($cluster >= 2 && $cluster < 0xfff8 && $visited < $this->clusterCount) {
            $next = $this->readFat($cluster);
            if (!$this->writeFat($cluster, 0)) {
                return false;
            }
            $cluster = $next;
            $visited++;
        }
        return true;
    }

    private function findFreeCluster(int $start): int
    {
        $lastCluster = $this->clusterCount + 1;
        for ($cluster = $start; $cluster <= $lastCluster; $cluster++) {
            if ($this->readFat($cluster) === 0) {
                return $cluster;
            }
        }
        return -1;
    }

    private function clearCluster(int $cluster): bool
    {
        $blank = $this->blankSector();
        $firstSector = $this->clusterSector($cluster);
        for ($index = 0; $index < $this->sectorsPerCluster; $index++) {
            if (!$this->device->writeSector($firstSector + $index, $blank)) {
                return false;
            }
        }
        return true;
    }

    private function deleteEntry(int $location): bool
    {
        $sectorNumber = $this->quotient($location, 512);
        $sector = $this->device->readSector($sectorNumber);
        if (strlen($sector) !== 512) {
            return false;
        }
        $sector[$location % 512] = chr(0xe5);
        return $this->device->writeSector($sectorNumber, $sector);
    }

    private function directoryIsEmpty(int $directoryCluster): bool
    {
        $cluster = $directoryCluster;
        $visited = 0;
        while ($cluster >= 2 && $cluster < 0xfff8 && $visited < $this->clusterCount) {
            $firstSector = $this->clusterSector($cluster);
            for ($sectorIndex = 0; $sectorIndex < $this->sectorsPerCluster; $sectorIndex++) {
                $sector = $this->device->readSector($firstSector + $sectorIndex);
                if (strlen($sector) !== 512) {
                    return false;
                }
                for ($offset = 0; $offset < 512; $offset += 32) {
                    $first = $this->byteAt($sector, $offset);
                    if ($first === 0x00) {
                        return true;
                    }
                    if ($first !== 0xe5 && $first !== 0x2e) {
                        return false;
                    }
                }
            }
            $cluster = $this->readFat($cluster);
            $visited++;
        }
        return true;
    }

    public function hasRootEntry(string $name): bool
    {
        $fatName = $this->fatName($name);
        return strlen($fatName) === 11 && $this->rootEntryLocation($fatName) >= 0;
    }

    public function rootEntryType(string $name): int
    {
        $fatName = $this->fatName($name);
        if (strlen($fatName) !== 11) {
            return 0;
        }
        $location = $this->rootEntryLocation($fatName);
        if ($location < 0) {
            return 0;
        }
        $entry = $this->readEntry($location);
        return ($this->byteAt($entry, 11) & 0x10) !== 0 ? 2 : 1;
    }

    public function rootFileSize(string $name): int
    {
        $fatName = $this->fatName($name);
        if (strlen($fatName) !== 11) {
            return -1;
        }
        $location = $this->rootEntryLocation($fatName);
        if ($location < 0) {
            return -1;
        }
        $entry = $this->readEntry($location);
        return ($this->byteAt($entry, 11) & 0x10) !== 0
            ? 0
            : $this->readU32($entry, 28);
    }

    public function pathEntryType(string $path): int
    {
        if ($path === '' || $path === '/' || $path === '.') {
            return 2;
        }
        $location = $this->pathEntryLocation($path);
        if ($location < 0) {
            return 0;
        }
        $entry = $this->readEntry($location);
        return ($this->byteAt($entry, 11) & 0x10) !== 0 ? 2 : 1;
    }

    public function pathFileSize(string $path): int
    {
        $location = $this->pathEntryLocation($path);
        if ($location < 0) {
            return -1;
        }
        $entry = $this->readEntry($location);
        return ($this->byteAt($entry, 11) & 0x10) !== 0
            ? 0
            : $this->readU32($entry, 28);
    }

    public function makePathDirectory(string $path): bool
    {
        $name = $this->pathLeafName($path);
        $fatName = $this->fatName($name);
        $parentCluster = $this->pathParentCluster($path);
        if (strlen($fatName) !== 11 || $parentCluster < 0
            || $this->directoryEntryLocation($parentCluster, $fatName) >= 0) {
            return false;
        }
        $location = $this->freeDirectoryEntryLocation($parentCluster);
        $cluster = $this->findFreeCluster(2);
        if ($location < 0 || $cluster < 0 || !$this->writeFat($cluster, 0xffff)
            || !$this->clearCluster($cluster)) {
            return false;
        }
        $sector = $this->device->readSector($this->clusterSector($cluster));
        $dot = '.          ';
        $dotDot = '..         ';
        for ($index = 0; $index < 11; $index++) {
            $sector[$index] = $dot[$index];
            $sector[32 + $index] = $dotDot[$index];
        }
        $sector[11] = chr(0x10);
        $sector[43] = chr(0x10);
        $this->writeU16($sector, 26, $cluster);
        $this->writeU16($sector, 58, $parentCluster);
        if (!$this->device->writeSector($this->clusterSector($cluster), $sector)
            || !$this->writeEntry($location, $fatName, 0x10, $cluster, 0)) {
            $this->writeFat($cluster, 0);
            return false;
        }
        return $this->device->flush();
    }

    public function writePathFile(string $path, string $contents): bool
    {
        $fatName = $this->fatName($this->pathLeafName($path));
        $parentCluster = $this->pathParentCluster($path);
        if (strlen($fatName) !== 11 || $parentCluster < 0) {
            return false;
        }
        $location = $this->directoryEntryLocation($parentCluster, $fatName);
        if ($location >= 0) {
            $oldEntry = $this->readEntry($location);
            if (($this->byteAt($oldEntry, 11) & 0x10) !== 0
                || !$this->freeChain($this->readU16($oldEntry, 26))) {
                return false;
            }
        } else {
            $location = $this->freeDirectoryEntryLocation($parentCluster);
            if ($location < 0) {
                return false;
            }
        }

        $clusterSize = $this->sectorsPerCluster * 512;
        $needed = $this->ceilQuotient(strlen($contents), $clusterSize);
        $clusters = std::vector(Type::Int);
        $candidate = 2;
        for ($index = 0; $index < $needed; $index++) {
            $candidate = $this->findFreeCluster($candidate);
            if ($candidate < 0) {
                for ($rollback = 0; $rollback < count($clusters); $rollback++) {
                    $this->writeFat($clusters[$rollback], 0);
                }
                return false;
            }
            $clusters[] = $candidate;
            if (!$this->writeFat($candidate, 0xffff)) {
                return false;
            }
            $candidate++;
        }
        for ($index = 0; $index < count($clusters); $index++) {
            $cluster = $clusters[$index];
            $next = $index + 1 < count($clusters) ? $clusters[$index + 1] : 0xffff;
            if (!$this->writeFat($cluster, $next)) {
                return false;
            }
            for ($sectorIndex = 0; $sectorIndex < $this->sectorsPerCluster; $sectorIndex++) {
                $dataOffset = $index * $clusterSize + $sectorIndex * 512;
                $part = substr($contents, $dataOffset, 512);
                if (!$this->device->writeSector(
                    $this->clusterSector($cluster) + $sectorIndex,
                    $this->sectorData($part)
                )) {
                    return false;
                }
            }
        }
        $firstCluster = count($clusters) === 0 ? 0 : $clusters[0];
        return $this->writeEntry($location, $fatName, 0x20, $firstCluster, strlen($contents))
            && $this->device->flush();
    }

    public function makeRootDirectory(string $name): bool
    {
        $fatName = $this->fatName($name);
        if (strlen($fatName) !== 11 || $this->rootEntryLocation($fatName) >= 0) {
            return false;
        }
        $location = $this->freeRootEntryLocation();
        $cluster = $this->findFreeCluster(2);
        if ($location < 0 || $cluster < 0 || !$this->writeFat($cluster, 0xffff)
            || !$this->clearCluster($cluster)) {
            return false;
        }

        $sector = $this->device->readSector($this->clusterSector($cluster));
        $dot = '.          ';
        $dotDot = '..         ';
        for ($index = 0; $index < 11; $index++) {
            $sector[$index] = $dot[$index];
            $sector[32 + $index] = $dotDot[$index];
        }
        $sector[11] = chr(0x10);
        $sector[43] = chr(0x10);
        $this->writeU16($sector, 26, $cluster);
        $this->writeU16($sector, 58, 0);
        if (!$this->device->writeSector($this->clusterSector($cluster), $sector)
            || !$this->writeEntry($location, $fatName, 0x10, $cluster, 0)) {
            $this->writeFat($cluster, 0);
            return false;
        }
        return $this->device->flush();
    }

    public function writeRootFile(string $name, string $contents): bool
    {
        $fatName = $this->fatName($name);
        if (strlen($fatName) !== 11) {
            return false;
        }
        $location = $this->rootEntryLocation($fatName);
        if ($location >= 0) {
            $oldEntry = $this->readEntry($location);
            if (($this->byteAt($oldEntry, 11) & 0x10) !== 0
                || !$this->freeChain($this->readU16($oldEntry, 26))) {
                return false;
            }
        } else {
            $location = $this->freeRootEntryLocation();
            if ($location < 0) {
                return false;
            }
        }

        $clusterSize = $this->sectorsPerCluster * 512;
        $needed = $this->ceilQuotient(strlen($contents), $clusterSize);
        $clusters = std::vector(Type::Int);
        $candidate = 2;
        for ($index = 0; $index < $needed; $index++) {
            $candidate = $this->findFreeCluster($candidate);
            if ($candidate < 0) {
                for ($rollback = 0; $rollback < count($clusters); $rollback++) {
                    $this->writeFat($clusters[$rollback], 0);
                }
                return false;
            }
            $clusters[] = $candidate;
            if (!$this->writeFat($candidate, 0xffff)) {
                return false;
            }
            $candidate++;
        }

        for ($index = 0; $index < count($clusters); $index++) {
            $cluster = $clusters[$index];
            $next = $index + 1 < count($clusters) ? $clusters[$index + 1] : 0xffff;
            if (!$this->writeFat($cluster, $next)) {
                return false;
            }
            for ($sectorIndex = 0; $sectorIndex < $this->sectorsPerCluster; $sectorIndex++) {
                $dataOffset = $index * $clusterSize + $sectorIndex * 512;
                $part = substr($contents, $dataOffset, 512);
                if (!$this->device->writeSector(
                    $this->clusterSector($cluster) + $sectorIndex,
                    $this->sectorData($part)
                )) {
                    return false;
                }
            }
        }

        $firstCluster = count($clusters) === 0 ? 0 : $clusters[0];
        return $this->writeEntry($location, $fatName, 0x20, $firstCluster, strlen($contents))
            && $this->device->flush();
    }

    public function readRootFile(string $name): string
    {
        $fatName = $this->fatName($name);
        if (strlen($fatName) !== 11) {
            return '';
        }
        $location = $this->rootEntryLocation($fatName);
        if ($location < 0) {
            return '';
        }
        $entry = $this->readEntry($location);
        if (($this->byteAt($entry, 11) & 0x10) !== 0) {
            return '';
        }
        $remaining = $this->readU32($entry, 28);
        $cluster = $this->readU16($entry, 26);
        $parts = [];
        $visited = 0;
        while ($remaining > 0 && $cluster >= 2 && $cluster < 0xfff8
            && $visited < $this->clusterCount) {
            $firstSector = $this->clusterSector($cluster);
            for ($index = 0; $index < $this->sectorsPerCluster && $remaining > 0; $index++) {
                $sector = $this->device->readSector($firstSector + $index);
                if (strlen($sector) !== 512) {
                    return '';
                }
                $length = $remaining < 512 ? $remaining : 512;
                $parts[] = substr($sector, 0, $length);
                $remaining -= $length;
            }
            $cluster = $this->readFat($cluster);
            $visited++;
        }
        return $remaining === 0 ? implode('', $parts) : '';
    }

    public function readPathFile(string $path): string
    {
        $location = $this->pathEntryLocation($path);
        if ($location < 0) {
            return '';
        }
        $entry = $this->readEntry($location);
        if (($this->byteAt($entry, 11) & 0x10) !== 0) {
            return '';
        }
        $remaining = $this->readU32($entry, 28);
        $cluster = $this->readU16($entry, 26);
        $parts = [];
        $visited = 0;
        while ($remaining > 0 && $cluster >= 2 && $cluster < 0xfff8
            && $visited < $this->clusterCount) {
            $firstSector = $this->clusterSector($cluster);
            for ($index = 0; $index < $this->sectorsPerCluster && $remaining > 0; $index++) {
                $sector = $this->device->readSector($firstSector + $index);
                if (strlen($sector) !== 512) {
                    return '';
                }
                $partLength = $remaining < 512 ? $remaining : 512;
                $parts[] = substr($sector, 0, $partLength);
                $remaining -= $partLength;
            }
            $cluster = $this->readFat($cluster);
            $visited++;
        }
        return $remaining === 0 ? implode('', $parts) : '';
    }

    public function removeRootFile(string $name): bool
    {
        $fatName = $this->fatName($name);
        if (strlen($fatName) !== 11) {
            return false;
        }
        $location = $this->rootEntryLocation($fatName);
        if ($location < 0) {
            return false;
        }
        $entry = $this->readEntry($location);
        if (($this->byteAt($entry, 11) & 0x10) !== 0
            || !$this->freeChain($this->readU16($entry, 26))) {
            return false;
        }
        $sectorNumber = $this->quotient($location, 512);
        $sector = $this->device->readSector($sectorNumber);
        $sector[$location % 512] = chr(0xe5);
        return $this->device->writeSector($sectorNumber, $sector)
            && $this->device->flush();
    }

    public function removePathFile(string $path): bool
    {
        $location = $this->pathEntryLocation($path);
        if ($location < 0) {
            return false;
        }
        $entry = $this->readEntry($location);
        if (($this->byteAt($entry, 11) & 0x10) !== 0
            || !$this->freeChain($this->readU16($entry, 26))) {
            return false;
        }
        return $this->deleteEntry($location) && $this->device->flush();
    }

    public function renameRootEntry(string $oldName, string $newName): bool
    {
        $oldFatName = $this->fatName($oldName);
        $newFatName = $this->fatName($newName);
        if (strlen($oldFatName) !== 11 || strlen($newFatName) !== 11
            || $this->rootEntryLocation($newFatName) >= 0) {
            return false;
        }
        $location = $this->rootEntryLocation($oldFatName);
        if ($location < 0) {
            return false;
        }
        $sectorNumber = $this->quotient($location, 512);
        $offset = $location % 512;
        $sector = $this->device->readSector($sectorNumber);
        for ($index = 0; $index < 11; $index++) {
            $sector[$offset + $index] = $newFatName[$index];
        }
        return $this->device->writeSector($sectorNumber, $sector)
            && $this->device->flush();
    }

    public function renamePathEntry(string $oldPath, string $newPath): bool
    {
        $oldParent = $this->pathParentCluster($oldPath);
        $newParent = $this->pathParentCluster($newPath);
        $newFatName = $this->fatName($this->pathLeafName($newPath));
        if ($oldParent < 0 || $newParent !== $oldParent || strlen($newFatName) !== 11
            || $this->directoryEntryLocation($newParent, $newFatName) >= 0) {
            return false;
        }
        $location = $this->pathEntryLocation($oldPath);
        if ($location < 0) {
            return false;
        }
        $sectorNumber = $this->quotient($location, 512);
        $offset = $location % 512;
        $sector = $this->device->readSector($sectorNumber);
        if (strlen($sector) !== 512) {
            return false;
        }
        for ($index = 0; $index < 11; $index++) {
            $sector[$offset + $index] = $newFatName[$index];
        }
        return $this->device->writeSector($sectorNumber, $sector)
            && $this->device->flush();
    }

    public function removeRootDirectory(string $name): bool
    {
        $fatName = $this->fatName($name);
        if (strlen($fatName) !== 11) {
            return false;
        }
        $location = $this->rootEntryLocation($fatName);
        if ($location < 0) {
            return false;
        }
        $entry = $this->readEntry($location);
        if (($this->byteAt($entry, 11) & 0x10) === 0) {
            return false;
        }
        $cluster = $this->readU16($entry, 26);
        $firstSector = $this->device->readSector($this->clusterSector($cluster));
        for ($offset = 64; $offset < 512; $offset += 32) {
            $first = $this->byteAt($firstSector, $offset);
            if ($first !== 0x00 && $first !== 0xe5) {
                return false;
            }
        }
        if (!$this->freeChain($cluster)) {
            return false;
        }
        $sectorNumber = $this->quotient($location, 512);
        $sector = $this->device->readSector($sectorNumber);
        $sector[$location % 512] = chr(0xe5);
        return $this->device->writeSector($sectorNumber, $sector)
            && $this->device->flush();
    }

    public function removePathDirectory(string $path): bool
    {
        $location = $this->pathEntryLocation($path);
        if ($location < 0) {
            return false;
        }
        $entry = $this->readEntry($location);
        if (($this->byteAt($entry, 11) & 0x10) === 0) {
            return false;
        }
        $cluster = $this->readU16($entry, 26);
        if (!$this->directoryIsEmpty($cluster) || !$this->freeChain($cluster)) {
            return false;
        }
        return $this->deleteEntry($location) && $this->device->flush();
    }

    public function rootEntryNames(): string
    {
        $result = '';
        for ($sectorIndex = 0; $sectorIndex < $this->rootSectorCount; $sectorIndex++) {
            $sector = $this->device->readSector($this->firstRootSector + $sectorIndex);
            if (strlen($sector) !== 512) {
                return '';
            }
            for ($offset = 0; $offset < 512; $offset += 32) {
                $first = $this->byteAt($sector, $offset);
                if ($first === 0x00) {
                    return $result;
                }
                $attributes = $this->byteAt($sector, $offset + 11);
                if ($first === 0xe5 || $attributes === 0x0f || ($attributes & 0x08) !== 0) {
                    continue;
                }
                $result .= $this->displayName(substr($sector, $offset, 11)) . chr(10);
            }
        }
        return $result;
    }

    public function pathEntryNames(string $path): string
    {
        $directoryCluster = $this->pathDirectoryCluster($path);
        if ($directoryCluster < 0) {
            return '';
        }
        if ($directoryCluster === 0) {
            return $this->rootEntryNames();
        }
        $result = '';
        $cluster = $directoryCluster;
        $visited = 0;
        while ($cluster >= 2 && $cluster < 0xfff8 && $visited < $this->clusterCount) {
            $firstSector = $this->clusterSector($cluster);
            for ($sectorIndex = 0; $sectorIndex < $this->sectorsPerCluster; $sectorIndex++) {
                $sector = $this->device->readSector($firstSector + $sectorIndex);
                if (strlen($sector) !== 512) {
                    return '';
                }
                for ($offset = 0; $offset < 512; $offset += 32) {
                    $first = $this->byteAt($sector, $offset);
                    if ($first === 0x00) {
                        return $result;
                    }
                    $attributes = $this->byteAt($sector, $offset + 11);
                    if ($first === 0xe5 || $first === 0x2e || $attributes === 0x0f
                        || ($attributes & 0x08) !== 0) {
                        continue;
                    }
                    $result .= $this->displayName(substr($sector, $offset, 11)) . chr(10);
                }
            }
            $cluster = $this->readFat($cluster);
            $visited++;
        }
        return $result;
    }

    public function rootListing(): string
    {
        $result = '';
        for ($sectorIndex = 0; $sectorIndex < $this->rootSectorCount; $sectorIndex++) {
            $sector = $this->device->readSector($this->firstRootSector + $sectorIndex);
            if (strlen($sector) !== 512) {
                return '';
            }
            for ($offset = 0; $offset < 512; $offset += 32) {
                $first = $this->byteAt($sector, $offset);
                if ($first === 0x00) {
                    return $result;
                }
                $attributes = $this->byteAt($sector, $offset + 11);
                if ($first === 0xe5 || $attributes === 0x0f || ($attributes & 0x08) !== 0) {
                    continue;
                }
                if (strlen($result) !== 0) {
                    $result .= ', ';
                }
                $result .= $this->displayName(substr($sector, $offset, 11));
                if (($attributes & 0x10) !== 0) {
                    $result .= '/';
                }
            }
        }
        return $result;
    }
}
