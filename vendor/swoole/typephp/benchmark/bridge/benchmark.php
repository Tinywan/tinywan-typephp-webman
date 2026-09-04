<?php

declare(strict_types=1);

const BRIDGE_ITERATIONS = 10_000_000;
const BRIDGE_CONTAINER_ITERATIONS = 1_000_000;
const BRIDGE_ROUNDS = 5;

function bridgePureInt(int $iterations): int
{
    $sum = 0;
    for ($i = 0; $i < $iterations; $i++) {
        $sum += $i * 2 + 1;
    }
    return $sum;
}

function bridgeAddOne(int $value): int
{
    return $value + 1;
}

function bridgeFunctionCall(int $iterations): int
{
    $sum = 0;
    for ($i = 0; $i < $iterations; $i++) {
        $sum += bridgeAddOne($i);
    }
    return $sum;
}

final class BridgeCalculator
{
    public function hit(int $value): int
    {
        return $value + 1;
    }
}

function bridgeMethodCall(int $iterations): int
{
    $calculator = new BridgeCalculator();
    $sum = 0;
    for ($i = 0; $i < $iterations; $i++) {
        $sum += $calculator->hit($i);
    }
    return $sum;
}

final class BridgeCounter
{
    public int $value = 0;
}

function bridgePropertyAccess(int $iterations): int
{
    $counter = new BridgeCounter();
    for ($i = 0; $i < $iterations; $i++) {
        $counter->value++;
    }
    return $counter->value;
}

final class BridgeMagicCall
{
    public function __call(string $name, array $arguments): int
    {
        return $arguments[0] + 1;
    }
}

function bridgeMagicCall(int $iterations): int
{
    $object = new BridgeMagicCall();
    $sum = 0;
    for ($i = 0; $i < $iterations; $i++) {
        $sum += $object->hit($i);
    }
    return $sum;
}

final class BridgeMagicProperty
{
    private array $data = ['value' => 0];

    public function __get(string $name): int
    {
        return $this->data[$name];
    }

    public function __set(string $name, mixed $value): void
    {
        $this->data[$name] = $value;
    }
}

function bridgeMagicProperty(int $iterations): int
{
    $object = new BridgeMagicProperty();
    for ($i = 0; $i < $iterations; $i++) {
        $object->value = $object->value + 1;
    }
    return $object->value;
}

function bridgeArrayAppend(int $iterations): int
{
    $values = [];
    for ($i = 0; $i < $iterations; $i++) {
        $values[] = $i;
    }
    return count($values);
}

function bridgeStringConcat(int $iterations): string
{
    $value = '';
    for ($i = 0; $i < $iterations; $i++) {
        $value .= 'x';
    }
    return $value;
}

function measureBridgeCase(string $case): array
{
    $iterations = match ($case) {
        'array_append', 'string_concat' => BRIDGE_CONTAINER_ITERATIONS,
        default => BRIDGE_ITERATIONS,
    };
    $best = 0;
    $bestResult = null;
    for ($round = 0; $round < BRIDGE_ROUNDS; $round++) {
        $start = hrtime(true);
        $result = match ($case) {
            'pure_int' => bridgePureInt($iterations),
            'function_call' => bridgeFunctionCall($iterations),
            'method_call' => bridgeMethodCall($iterations),
            'property_access' => bridgePropertyAccess($iterations),
            'magic_call' => bridgeMagicCall($iterations),
            'magic_property' => bridgeMagicProperty($iterations),
            'array_append' => bridgeArrayAppend($iterations),
            'string_concat' => bridgeStringConcat($iterations),
            default => throw new RuntimeException("Unknown benchmark case: {$case}"),
        };
        $elapsed = hrtime(true) - $start;
        if ($round === 0 || $elapsed < $best) {
            $best = $elapsed;
            $bestResult = $result;
        }
    }
    return [$best / $iterations, $bestResult];
}

function main(): void
{
    bridgePureInt(1000);
    bridgeFunctionCall(1000);
    bridgeMethodCall(1000);
    bridgePropertyAccess(1000);
    bridgeMagicCall(1000);
    bridgeMagicProperty(1000);
    bridgeArrayAppend(1000);
    bridgeStringConcat(1000);

    $selectedCase = getenv('BRIDGE_CASE');
    foreach ([
        'pure_int',
        'function_call',
        'method_call',
        'property_access',
        'magic_call',
        'magic_property',
        'array_append',
        'string_concat',
    ] as $case) {
        if (is_string($selectedCase) && $selectedCase !== '' && $case !== $selectedCase) {
            continue;
        }
        [$nanoseconds, $result] = measureBridgeCase($case);
        printf("%s_ns=%.3f\n", $case, $nanoseconds);
        printf("checksum_%s=%s\n", $case, is_string($result) ? strlen($result) : $result);
    }
}
