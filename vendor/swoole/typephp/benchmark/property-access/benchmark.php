<?php

declare(strict_types=1);

final class DynamicPropertyEntity
{
    public int $first = 0;
    public int $second = 0;
    public int $third = 0;
    public int $fourth = 0;
    public int $fifth = 0;

    public function hydrate(array $data): void
    {
        foreach ($data as $property => $value) {
            $this->$property = $value;
        }
    }

    public function sum(array $properties): int
    {
        $sum = 0;
        foreach ($properties as $property) {
            $sum += $this->$property;
        }
        return $sum;
    }
}

final class StaticPropertyEntity
{
    public int $first = 0;
    public int $second = 0;
    public int $third = 0;
    public int $fourth = 0;
    public int $fifth = 0;

    public function hydrate(array $data): void
    {
        $this->first = $data['first'];
        $this->second = $data['second'];
        $this->third = $data['third'];
        $this->fourth = $data['fourth'];
        $this->fifth = $data['fifth'];
    }

    public function sum(): int
    {
        return $this->first + $this->second + $this->third + $this->fourth + $this->fifth;
    }
}

function runDynamicWrite(DynamicPropertyEntity $entity, array $data, int $iterations): int
{
    for ($i = 0; $i < $iterations; $i++) {
        $entity->hydrate($data);
    }
    return $entity->first;
}

function runStaticWrite(StaticPropertyEntity $entity, array $data, int $iterations): int
{
    for ($i = 0; $i < $iterations; $i++) {
        $entity->hydrate($data);
    }
    return $entity->first;
}

function runDynamicRead(DynamicPropertyEntity $entity, array $properties, int $iterations): int
{
    $sum = 0;
    for ($i = 0; $i < $iterations; $i++) {
        $sum += $entity->sum($properties);
    }
    return $sum;
}

function runStaticRead(StaticPropertyEntity $entity, int $iterations): int
{
    $sum = 0;
    for ($i = 0; $i < $iterations; $i++) {
        $sum += $entity->sum();
    }
    return $sum;
}

function measure(callable $callback, int $operations): float
{
    global $benchmarkSink;
    for ($warmup = 0; $warmup < 3; $warmup++) {
        $benchmarkSink += $callback();
    }

    $best = 1.0e30;
    for ($round = 0; $round < 7; $round++) {
        $start = hrtime(true);
        $result = $callback();
        $elapsed = hrtime(true) - $start;
        $benchmarkSink += $result;
        if ($elapsed < $best) {
            $best = $elapsed;
        }
    }
    return $best / $operations;
}

function main(): void
{
    global $benchmarkSink;
    $benchmarkSink = 0;
    $iterations = 200000;
    $data = [
        'first' => 1,
        'second' => 2,
        'third' => 3,
        'fourth' => 4,
        'fifth' => 5,
    ];
    $properties = ['first', 'second', 'third', 'fourth', 'fifth'];
    $dynamic = new DynamicPropertyEntity();
    $static = new StaticPropertyEntity();
    $operations = $iterations * 5;

    $dynamicWrite = measure(
        function () use ($dynamic, $data, $iterations): int {
            return runDynamicWrite($dynamic, $data, $iterations);
        },
        $operations,
    );
    $staticWrite = measure(
        function () use ($static, $data, $iterations): int {
            return runStaticWrite($static, $data, $iterations);
        },
        $operations,
    );
    $dynamicRead = measure(
        function () use ($dynamic, $properties, $iterations): int {
            return runDynamicRead($dynamic, $properties, $iterations);
        },
        $operations,
    );
    $staticRead = measure(
        function () use ($static, $iterations): int {
            return runStaticRead($static, $iterations);
        },
        $operations,
    );

    printf("dynamic_write_ns=%.3f\n", $dynamicWrite);
    printf("static_write_ns=%.3f\n", $staticWrite);
    printf("dynamic_read_ns=%.3f\n", $dynamicRead);
    printf("static_read_ns=%.3f\n", $staticRead);
    echo 'checksum=', $benchmarkSink + $dynamic->sum($properties) + $static->sum(), "\n";
}
