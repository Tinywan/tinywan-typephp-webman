--TEST--
Closure calls preserve strict builtin argument validation on dynamic fallback
--FILE--
<?php
declare(strict_types=1);

function mixedInt(): mixed
{
    return 1;
}

function main(): void
{
    $calls = [
        'arrow' => static fn() => in_array('1', [1], mixedInt()),
        'closure' => static function (): float {
            return sin('1');
        },
        'round' => static fn() => round('1.25'),
        'floor' => static function (): float {
            return floor('1.5');
        },
    ];

    foreach ($calls as $name => $call) {
        try {
            $call();
            echo $name, "=missing TypeError\n";
        } catch (TypeError $error) {
            echo $name, "=TypeError\n";
        }
    }
}
?>
--EXPECT--
arrow=TypeError
closure=TypeError
round=TypeError
floor=TypeError
