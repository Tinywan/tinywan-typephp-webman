<?php

class PropertyDefaultNullForInt
{
    public int $a = null;
}

function property_default_null_for_int(): void
{
    $test = new PropertyDefaultNullForInt();
}
