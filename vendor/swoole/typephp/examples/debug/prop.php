<?php
declare(strict_types=1);
class Data {
    public int $value = 0;
    public bool $bv = true;
}

function main()
{
    $o = new Data;
    $value = any('222');
    $o->value = $value;
    $o->value += '333';
    var_dump($o->value);

    $o->value = 'str';
    $o->value += 'str';
    var_dump($o->value);
}