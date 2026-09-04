<?php

declare(strict_types=1);

const DYNAMIC_CALL_ITERATIONS = 1_000_000;
const DYNAMIC_CALL_ROUNDS = 5;

function dynamicCallAddOne(int $value): int
{
    return $value + 1;
}

function dynamicCallAddTwo(int $value): int
{
    return $value + 2;
}

function dynamicCallAddThree(int $value): int
{
    return $value + 3;
}

function dynamicCallAddFour(int $value): int
{
    return $value + 4;
}

function dynamicCallAddFive(int $value): int
{
    return $value + 5;
}

function dynamicCallAddSix(int $value): int
{
    return $value + 6;
}

function dynamicCallAddSeven(int $value): int
{
    return $value + 7;
}

function dynamicCallAddEight(int $value): int
{
    return $value + 8;
}

final class DynamicCallTarget
{
    public static function addOne(int $value): int
    {
        return $value + 1;
    }

    public function addTwo(int $value): int
    {
        return $value + 2;
    }

    public function hitOne(int $value): int
    {
        return $value + 1;
    }

    public function hitTwo(int $value): int
    {
        return $value + 2;
    }

    public function __invoke(int $value): int
    {
        return $value + 3;
    }
}

final class DynamicCallAlternateTarget
{
    public function hitOne(int $value): int
    {
        return $value + 1;
    }
}

function runDirectCall(int $iterations): int
{
    $sum = 0;
    for ($i = 0; $i < $iterations; $i++) {
        $sum += dynamicCallAddOne($i);
    }
    return $sum;
}

function runMonomorphicStringCall(int $iterations): int
{
    $callback = 'dynamicCallAddOne';
    $sum = 0;
    for ($i = 0; $i < $iterations; $i++) {
        $sum += $callback($i);
    }
    return $sum;
}

function runAlternatingStringCall(int $iterations): int
{
    $sum = 0;
    for ($i = 0; $i < $iterations; $i++) {
        $callback = ($i & 1) === 0 ? 'dynamicCallAddOne' : 'dynamicCallAddTwo';
        $sum += $callback($i);
    }
    return $sum;
}

function runMegamorphicStringCall(int $iterations): int
{
    $callbacks = [
        'dynamicCallAddOne',
        'dynamicCallAddTwo',
        'dynamicCallAddThree',
        'dynamicCallAddFour',
        'dynamicCallAddFive',
        'dynamicCallAddSix',
        'dynamicCallAddSeven',
        'dynamicCallAddEight',
    ];
    $sum = 0;
    for ($i = 0; $i < $iterations; $i++) {
        $callback = $callbacks[($i * 5 + 3) & 7];
        $sum += $callback($i);
    }
    return $sum;
}

function runMonomorphicClosureCall(int $iterations): int
{
    $callback = static fn (int $value): int => $value + 1;
    $sum = 0;
    for ($i = 0; $i < $iterations; $i++) {
        $sum += $callback($i);
    }
    return $sum;
}

function runAlternatingClosureCall(int $iterations): int
{
    $first = static fn (int $value): int => $value + 1;
    $second = static fn (int $value): int => $value + 2;
    $sum = 0;
    for ($i = 0; $i < $iterations; $i++) {
        $callback = ($i & 1) === 0 ? $first : $second;
        $sum += $callback($i);
    }
    return $sum;
}

function runStaticMethodStringCall(int $iterations): int
{
    $callback = 'DynamicCallTarget::addOne';
    $sum = 0;
    for ($i = 0; $i < $iterations; $i++) {
        $sum += $callback($i);
    }
    return $sum;
}

function runObjectMethodArrayCall(int $iterations): int
{
    $target = new DynamicCallTarget();
    $callback = [$target, 'addTwo'];
    $sum = 0;
    for ($i = 0; $i < $iterations; $i++) {
        $sum += $callback($i);
    }
    return $sum;
}

function runInvokableObjectCall(int $iterations): int
{
    $callback = new DynamicCallTarget();
    $sum = 0;
    for ($i = 0; $i < $iterations; $i++) {
        $sum += $callback($i);
    }
    return $sum;
}

function runMonomorphicMethodNameCall(int $iterations): int
{
    $target = new DynamicCallTarget();
    $method = 'hitOne';
    $sum = 0;
    for ($i = 0; $i < $iterations; $i++) {
        $sum += $target->$method($i);
    }
    return $sum;
}

function runAlternatingMethodNameCall(int $iterations): int
{
    $target = new DynamicCallTarget();
    $sum = 0;
    for ($i = 0; $i < $iterations; $i++) {
        $method = ($i & 1) === 0 ? 'hitOne' : 'hitTwo';
        $sum += $target->$method($i);
    }
    return $sum;
}

function runPolymorphicMethodReceiverCall(int $iterations): int
{
    $targets = [new DynamicCallTarget(), new DynamicCallAlternateTarget()];
    $method = 'hitOne';
    $sum = 0;
    for ($i = 0; $i < $iterations; $i++) {
        $target = $targets[$i & 1];
        $sum += $target->$method($i);
    }
    return $sum;
}

function runDynamicCallCase(string $case, int $iterations): int
{
    return match ($case) {
        'direct' => runDirectCall($iterations),
        'string_monomorphic' => runMonomorphicStringCall($iterations),
        'string_alternating' => runAlternatingStringCall($iterations),
        'string_megamorphic' => runMegamorphicStringCall($iterations),
        'closure_monomorphic' => runMonomorphicClosureCall($iterations),
        'closure_alternating' => runAlternatingClosureCall($iterations),
        'static_method_string' => runStaticMethodStringCall($iterations),
        'object_method_array' => runObjectMethodArrayCall($iterations),
        'invokable_object' => runInvokableObjectCall($iterations),
        'method_name_monomorphic' => runMonomorphicMethodNameCall($iterations),
        'method_name_alternating' => runAlternatingMethodNameCall($iterations),
        'method_receiver_polymorphic' => runPolymorphicMethodReceiverCall($iterations),
        default => throw new RuntimeException("Unknown benchmark case: {$case}"),
    };
}

function measureDynamicCallCase(string $case): array
{
    for ($warmup = 0; $warmup < 2; $warmup++) {
        runDynamicCallCase($case, 1_000);
    }

    $best = 1.0e30;
    $bestResult = 0;
    for ($round = 0; $round < DYNAMIC_CALL_ROUNDS; $round++) {
        $start = hrtime(true);
        $result = runDynamicCallCase($case, DYNAMIC_CALL_ITERATIONS);
        $elapsed = hrtime(true) - $start;
        if ($elapsed < $best) {
            $best = $elapsed;
            $bestResult = $result;
        }
    }

    return [$best / DYNAMIC_CALL_ITERATIONS, $bestResult];
}

function main(): void
{
    $selectedCase = getenv('DYNAMIC_CALL_CASE');
    foreach ([
        'direct',
        'string_monomorphic',
        'string_alternating',
        'string_megamorphic',
        'closure_monomorphic',
        'closure_alternating',
        'static_method_string',
        'object_method_array',
        'invokable_object',
        'method_name_monomorphic',
        'method_name_alternating',
        'method_receiver_polymorphic',
    ] as $case) {
        if (is_string($selectedCase) && $selectedCase !== '' && $selectedCase !== $case) {
            continue;
        }
        [$nanoseconds, $result] = measureDynamicCallCase($case);
        printf("%s_ns=%.3f\n", $case, $nanoseconds);
        printf("checksum_%s=%d\n", $case, $result);
    }
}
