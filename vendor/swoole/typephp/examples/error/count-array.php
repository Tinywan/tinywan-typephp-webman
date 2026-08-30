<?php

class FooArray
{
    public array $array;

    public function __construct(array $array)
    {
        $this->array = $array;
    }
}

class Bar
{
    public function run(array $array)
    {
        $n = count($array);
        var_dump($n);
    }
}

function main()
{
    $bar = new Bar();

    $foo = new FooArray([1, 2, 3]);

    $bar->run($foo->array);

}