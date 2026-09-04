--TEST--
Backed enum case constant expressions are folded before code generation
--FILE--
<?php

const TWO = 1 + 1;
const THREE = TWO + 1;

class Provider
{
    public const BASE = THREE + 1;
}

enum Number: int
{
    case Two = 1 + 1;
    case Three = THREE;
    case Four = Provider::BASE;
    case Five = [5][0];
    case Six = self::Two->value + 4;
    case Seven = self::Eight->value - 1;
    case Eight = 8;
}

enum Word: string
{
    case Hello = 'hel' . 'lo';
    case CaseName = Number::Two->name;
}

function main(): void
{
    foreach (Number::cases() as $case) {
        var_dump($case->value);
    }
    foreach (Word::cases() as $case) {
        var_dump($case->value);
    }
}
?>
--EXPECT--
int(2)
int(3)
int(4)
int(5)
int(6)
int(7)
int(8)
string(5) "hello"
string(3) "Two"
