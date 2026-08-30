<?php

class PropertyDefaultStringForInt
{
    public int $a = 'hello';
}

function property_default_string_for_int(): void
{
    $test = new PropertyDefaultStringForInt();
}
