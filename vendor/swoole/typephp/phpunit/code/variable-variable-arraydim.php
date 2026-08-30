<?php

function variable_variable_with_array_dim(): void
{
    $foo = ['bar' => 'hello'];
    ${$foo['bar']} = 'world';
    echo $hello;
}
