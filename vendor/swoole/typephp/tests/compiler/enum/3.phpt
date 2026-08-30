--TEST--
enum 2
--FILE--
<?php
enum T: string
{
    case I = 'int';
    case S = 'string';
}

final class TestSpa
{
    private const F = [
        '1' => ['d' => '', 't' => T::S],
        '2' => ['d' => 1, 't' => T::I],
        '3' => ['d' => 123, 't' => T::S],
    ];

    public static function E(): array
    {
        return self::F;
    }
}

function main()
{
    foreach (TestSpa::E() as $n => $f) {

        $ok = match ($f['t']) {
            T::I    => is_int($f['d']),
            T::S => is_string($f['d']),
        };

        echo $ok? " pass {$n} => [{$f['d']}, {$f['t']->value}]\n": " fail {$n} 元组非法\n";
    }
}
?>
--EXPECT--
pass 1 => [, string]
 pass 2 => [1, int]
 fail 3 元组非法