<?php

function variable_name(): string
{
    return 'hello';
}

function variable_variable_with_function_call(): void
{
    ${variable_name()} = 'world';
    echo $hello;
}
