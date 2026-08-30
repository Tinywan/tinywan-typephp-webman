<?php

use PhpParser\Node\Expr;

interface CompositeKnownInterface
{
}

function accepts_composite_known(CompositeKnownInterface|int $value): void
{
}

function main(): void
{
    accepts_composite_known(new Expr\Variable('value'));
}
