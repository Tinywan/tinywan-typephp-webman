<?php
use native_types;

const N = 1000_0000;
const ROUNDS = 5;

final class NativeCallTarget
{
    public function hit(int $i): int
    {
        return $i + 1;
    }
}

final class DynamicCallTarget
{
    public function hit(int $i): int
    {
        return $i + 1;
    }
}

final class EmptyObject
{
}

final class TemporaryCallTarget
{
    public function hit(int $i): int
    {
        return $i + 1;
    }
}

function baseline_loop(int $n): int
{
    $sum = 0;
    for ($i = 0; $i < $n; ++$i) {
        $sum += $i + 1;
    }
    return $sum;
}

function native_method_call(int $n): int
{
    $obj = new NativeCallTarget();
    $sum = 0;
    for ($i = 0; $i < $n; ++$i) {
        $sum += $obj->hit($i);
    }
    return $sum;
}

function dynamic_method_call(int $n): int
{
    global $dynamicObject;

    $sum = 0;
    for ($i = 0; $i < $n; ++$i) {
        $sum += $dynamicObject->hit($i);
    }
    return $sum;
}

function new_object_only(int $n): int
{
    $sum = 0;
    for ($i = 0; $i < $n; ++$i) {
        $obj = new EmptyObject();
        $sum += $obj instanceof EmptyObject ? 1 : 0;
    }
    return $sum;
}

function temporary_new_and_call(int $n): int
{
    $sum = 0;
    for ($i = 0; $i < $n; ++$i) {
        $sum += (new TemporaryCallTarget())->hit($i);
    }
    return $sum;
}

function empty_new_baseline(int $n): int
{
    $sum = 0;
    for ($i = 0; $i < $n; ++$i) {
        $sum += 1;
    }
    return $sum;
}

function elapsed_ns(string $case, int $n): array
{
    $start = hrtime(true);
    if ($case === 'baseline') {
        $result = baseline_loop($n);
    } elseif ($case === 'native') {
        $result = native_method_call($n);
    } elseif ($case === 'dynamic') {
        $result = dynamic_method_call($n);
    } elseif ($case === 'empty-new') {
        $result = empty_new_baseline($n);
    } elseif ($case === 'new') {
        $result = new_object_only($n);
    } elseif ($case === 'temporary') {
        $result = temporary_new_and_call($n);
    } else {
        throw new RuntimeException("unknown benchmark case: " . $case);
    }
    return [hrtime(true) - $start, $result];
}

function measure_min(string $case, int $n, int $rounds): array
{
    $bestNs = 0;
    $bestResult = 0;

    for ($i = 0; $i < $rounds; ++$i) {
        [$ns, $result] = elapsed_ns($case, $n);
        if ($i === 0 || $ns < $bestNs) {
            $bestNs = $ns;
            $bestResult = $result;
        }
    }

    return [$bestNs, $bestResult];
}

function report(string $name, int $ns, int $n, int $baselineNs = 0): void
{
    $adjusted = max(0, $ns - $baselineNs);
    printf(
        "%-20s raw=%0.6fs adjusted=%0.6fs ns/op=%0.2f\n",
        $name,
        $ns / 1000000000.0,
        $adjusted / 1000000000.0,
        $adjusted / (float) $n,
    );
}

function main(): void
{
    global $dynamicObject;

    $n = N;
    $dynamicObject = new DynamicCallTarget();

    baseline_loop(1000);
    native_method_call(1000);
    dynamic_method_call(1000);
    new_object_only(1000);
    temporary_new_and_call(1000);

    [$baseline, $baselineResult] = measure_min('baseline', $n, ROUNDS);
    [$native, $nativeResult] = measure_min('native', $n, ROUNDS);
    [$dynamic, $dynamicResult] = measure_min('dynamic', $n, ROUNDS);
    [$emptyNew, $emptyNewResult] = measure_min('empty-new', $n, ROUNDS);
    [$newObject, $newResult] = measure_min('new', $n, ROUNDS);
    [$temporary, $temporaryResult] = measure_min('temporary', $n, ROUNDS);

    printf("n=%d rounds=%d\n", $n, ROUNDS);
    report('baseline loop', $baseline, $n);
    report('native call', $native, $n, $baseline);
    report('dynamic call', $dynamic, $n, $baseline);
    report('newObject', $newObject, $n, $emptyNew);
    report('newObject + call', $temporary, $n, $baseline);

    printf(
        "checksums: baseline=%d native=%d dynamic=%d new=%d temporary=%d\n",
        $baselineResult,
        $nativeResult,
        $dynamicResult,
        $newResult + $emptyNewResult,
        $temporaryResult,
    );
}
