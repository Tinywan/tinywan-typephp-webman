<?php
function main(): void
{
    $arr = [1, 2];
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

    $value = 'old';
    $returnCapturedRef = function () use (&$value) {
        return $value;
    };
    $returnCapturedRef();
}
