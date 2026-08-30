<?php

function foo(mixed $arg) {
    echo __FUNCTION__;
    return;
    echo __FUNCTION__;
}

var_dump(foo(null));