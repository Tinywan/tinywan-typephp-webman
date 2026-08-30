<?php

class PropertyDefaultArrayForInt
{
    private int $a = [];

    public function __construct()
    {
        var_dump($this->a);
    }
}

function property_default_array_for_int(): void
{
    $test = new PropertyDefaultArrayForInt();
}
