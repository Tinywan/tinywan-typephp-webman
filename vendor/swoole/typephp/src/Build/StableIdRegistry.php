<?php

namespace TypePhp\Build;

/**
 * Append-only IDs used by generated translation units.
 *
 * IDs are deliberately never recycled. An object file from an earlier
 * incremental build can therefore keep referring to its old slot while other
 * source files are regenerated independently.
 */
final class StableIdRegistry
{
    private const int SCHEMA_VERSION = 2;

    /** @var array<string, array<string, int>> */
    private array $domains = [];
    /** @var array<string, int> */
    private array $nextIds = [];
    private bool $dirty = false;

    public function __construct(private readonly string $file)
    {
        $this->restore();
    }

    public function allocate(string $domain, string $key): int
    {
        // Prefix prevents a base64 result made only of digits from being cast
        // to an integer array key by PHP.
        $encoded = 'b:' . base64_encode($key);
        if (isset($this->domains[$domain][$encoded])) {
            return $this->domains[$domain][$encoded];
        }
        $id = $this->nextIds[$domain] ?? 0;
        $this->domains[$domain][$encoded] = $id;
        $this->nextIds[$domain] = $id + 1;
        $this->dirty = true;
        return $id;
    }

    public function capacity(string $domain): int
    {
        return $this->nextIds[$domain] ?? 0;
    }

    /** @return array<string, int> */
    public function entries(string $domain): array
    {
        $entries = [];
        foreach ($this->domains[$domain] ?? [] as $key => $id) {
            $decoded = str_starts_with($key, 'b:')
                ? base64_decode(substr($key, 2), true)
                : false;
            if ($decoded !== false) {
                $entries[$decoded] = $id;
            }
        }
        asort($entries, SORT_NUMERIC);
        return $entries;
    }

    public function flush(): void
    {
        if (!$this->dirty || $this->file === '') {
            return;
        }
        $contents = json_encode([
            'schema' => self::SCHEMA_VERSION,
            'domains' => $this->domains,
            'nextIds' => $this->nextIds,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
        AtomicFile::write($this->file, $contents, '.ids-');
        $this->dirty = false;
    }

    private function restore(): void
    {
        if ($this->file === '' || !is_file($this->file)) {
            return;
        }
        try {
            $contents = file_get_contents($this->file);
            $payload = is_string($contents)
                ? json_decode($contents, true, flags: JSON_THROW_ON_ERROR)
                : null;
            if (!is_array($payload)
                || ($payload['schema'] ?? null) !== self::SCHEMA_VERSION
                || !is_array($payload['domains'] ?? null)
                || !is_array($payload['nextIds'] ?? null)
            ) {
                return;
            }
            $domains = [];
            $nextIds = [];
            foreach ($payload['domains'] as $domain => $entries) {
                if (!is_string($domain) || !is_array($entries)) {
                    return;
                }
                $usedIds = [];
                foreach ($entries as $key => $id) {
                    if (!is_string($key)
                        || !str_starts_with($key, 'b:')
                        || base64_decode(substr($key, 2), true) === false
                        || !is_int($id)
                        || $id < 0
                        || isset($usedIds[$id])) {
                        return;
                    }
                    $domains[$domain][$key] = $id;
                    $usedIds[$id] = true;
                }
            }
            foreach ($payload['nextIds'] as $domain => $nextId) {
                if (is_string($domain) && is_int($nextId) && $nextId >= 0) {
                    $nextIds[$domain] = $nextId;
                }
            }
            // Never trust a persisted cursor to be smaller than an existing
            // entry. A truncated/manual cache edit must produce holes at worst,
            // never duplicate an ID already embedded in an object file.
            foreach ($domains as $domain => $entries) {
                $minimumNextId = max($entries) + 1;
                $nextIds[$domain] = max(
                    $nextIds[$domain] ?? 0,
                    $minimumNextId,
                );
            }
            $this->domains = $domains;
            $this->nextIds = $nextIds;
        } catch (\Throwable) {
            $this->domains = [];
            $this->nextIds = [];
        }
    }
}
