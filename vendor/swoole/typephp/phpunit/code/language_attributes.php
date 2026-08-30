<?php

namespace LanguageAttributes;

use \Constructor;
use \Getter;
use \MustUse;
use \NotEmpty;
use \NotNull;

function add(int $left, int $right): int
{
    $result = $left + $right;
    return $result;
}

#[MustUse]
function calculate(int $value): int
{
    return add($value, 1);
}

function nullable(#[NotNull] ?int $value): int
{
    return $value;
}

function nonEmpty(#[NotEmpty] string $value): string
{
    return $value;
}

class User
{
    #[Constructor, Getter]
    private int $id;

    #[Constructor, Getter]
    private string $name = 'typephp';

    #[MustUse]
    public function displayName(): string
    {
        return strtoupper($this->name);
    }
}

function main(): void
{
    $value = calculate(1);
    $user = new User(1);
    $name = $user->displayName();
    echo $value;
    echo $name;
    echo nullable(0);
    echo nonEmpty('0');
}
