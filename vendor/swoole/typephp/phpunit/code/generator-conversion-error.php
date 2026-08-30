<?php

function broken_generator(): iterable
{
    $name = 'value';
    yield $$name;
}
