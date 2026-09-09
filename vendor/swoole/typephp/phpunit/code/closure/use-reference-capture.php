<?php
function main(): void
{
    $arr = std::any([1, 2]);
    $copy = function () use ($arr) {
        $arr[] = 3;
        return $arr;
    };
    $copy();

    $ref = function () use (&$arr) {
        $arr[] = 4;
        return $arr;
    };
    $ref();

    $value = std::any('old');
    $returnCapturedRef = function () use (&$value) {
        return $value;
    };
    $returnCapturedRef();
}
