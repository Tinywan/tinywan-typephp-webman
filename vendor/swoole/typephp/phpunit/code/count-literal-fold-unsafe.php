<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

class KnownClass
{
    public const KNOWN = 1;
}

class MagicHolder
{
    public function __get(string $name): int
    {
        echo "get-{$name}\n";
        return 1;
    }
}

function bump(): int
{
    echo "bump\n";
    return 1;
}

function main(): void
{
    $rest = [1, 2, 3, 4, 5];
    $i = 0;
    $plain = 1;
    $ref = 1;
    $object = new MagicHolder();

    echo count([bump(), bump()]), "\n";
    echo count(['a' => 1, 'a' => 2]), "\n";
    echo count([...$rest, 9]), "\n";
    echo count([$i++, $i++]), "\n";
    echo count([$plain]), "\n";
    echo count([&$ref]), "\n";
    echo count([UNDEFINED_COUNT_LITERAL]), "\n";
    echo count([KnownClass::MISSING]), "\n";
    echo count(["{$object->property}"]), "\n";
    echo count([KnownClass::KNOWN]), "\n";
}
