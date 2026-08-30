<?php

namespace App;

use \Getter;

class User
{
    #[Getter]
    private string $name = 'Alice';

    #[\Getter]
    protected int $age = 18;

    #[Getter]
    public string $title = 'developer';

    #[Getter]
    public int $x = 1, $y = 2;

    public function __construct(
        #[Getter]
        private bool $active = true,
    ) {
    }
}

function main(): void
{
    $user = new User();
    var_dump($user->getName());
    var_dump($user->getAge());
    var_dump($user->getTitle());
    var_dump($user->getX());
    var_dump($user->getY());
    var_dump($user->getActive());
}
