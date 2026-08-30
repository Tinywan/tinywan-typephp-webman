<?php

use PhpParser\Node\Expr;

function accepts_php_parser_expr(Expr $expr): void
{
}

function main(): void
{
    accepts_php_parser_expr(new Expr\Variable('value'));
}
