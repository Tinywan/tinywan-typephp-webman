<?php

class PropertyDefaultValid
{
    public int $i = 123;
    public float $f = 1.5;
    public float $fromInt = 3;
    public string $s = 'hello';
    public bool $b = true;
    public array $arr = [];
    public ?int $ni = null;
    public int|array $ia = [];
    public mixed $m = [];
    public $untyped = [];
    public const NUM = 7;
    public int $fromConst = self::NUM;
}

function property_default_valid(): void
{
    $test = new PropertyDefaultValid();
    var_dump($test->i);
}
