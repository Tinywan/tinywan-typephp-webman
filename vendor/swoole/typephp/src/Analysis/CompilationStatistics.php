<?php

namespace TypePhp\Analysis;

/**
 * Usage information collected while TypePHP emits the final C++ program.
 *
 * This collector is intentionally independent from Nano. Build modules may
 * reuse it for dependency selection, diagnostics, or compilation reports.
 */
final class CompilationStatistics
{
    public const string FUNCTIONS = 'functions';
    public const string DIRECT_FUNCTIONS = 'direct-functions';
    public const string RUNTIME_FUNCTIONS = 'runtime-functions';
    public const string CLASSES = 'classes';
    public const string TYPES = 'types';
    public const string DYNAMIC_CAPABILITIES = 'dynamic-capabilities';

    /** @var array<string, array<string, int>> */
    private array $counters = [];

    private bool $collecting = false;

    public function begin(): void
    {
        $this->counters = [];
        $this->collecting = true;
    }

    public function finish(): void
    {
        $this->collecting = false;
    }

    public function isCollecting(): bool
    {
        return $this->collecting;
    }

    public function record(string $category, string $name): void
    {
        if (!$this->collecting || $category === '' || $name === '') {
            return;
        }
        $this->counters[$category][$name] = ($this->counters[$category][$name] ?? 0) + 1;
    }

    /** @return array<string, int> */
    public function get(string $category): array
    {
        $values = $this->counters[$category] ?? [];
        ksort($values, SORT_STRING);
        return $values;
    }

    public function has(string $category, string $name): bool
    {
        return isset($this->counters[$category][$name]);
    }

    /** @param array<mixed, mixed> $counters */
    public function merge(array $counters): void
    {
        foreach ($counters as $category => $values) {
            if (!is_string($category) || !is_array($values)) {
                continue;
            }
            foreach ($values as $name => $count) {
                if (is_string($name) && is_int($count) && $count > 0) {
                    $this->counters[$category][$name] = ($this->counters[$category][$name] ?? 0) + $count;
                }
            }
        }
    }

    /**
     * Return counters recorded since an earlier all() snapshot.
     *
     * @param array<string, array<string, int>> $before
     * @return array<string, array<string, int>>
     */
    public function delta(array $before): array
    {
        $delta = [];
        foreach ($this->counters as $category => $values) {
            foreach ($values as $name => $count) {
                $difference = $count - ($before[$category][$name] ?? 0);
                if ($difference > 0) {
                    $delta[$category][$name] = $difference;
                }
            }
        }
        return $delta;
    }

    /** @return array<string, array<string, int>> */
    public function all(): array
    {
        $result = $this->counters;
        ksort($result, SORT_STRING);
        foreach ($result as &$values) {
            ksort($values, SORT_STRING);
        }
        unset($values);
        return $result;
    }
}
