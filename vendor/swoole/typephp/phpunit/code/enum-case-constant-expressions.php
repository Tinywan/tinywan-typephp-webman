<?php

namespace EnumExpressionFixture;

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
}
