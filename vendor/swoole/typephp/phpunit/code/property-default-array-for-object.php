<?php

class PropertyDefaultArrayForObjectDep
{
}

class PropertyDefaultArrayForObject
{
    public PropertyDefaultArrayForObjectDep $dep = [];
}

function property_default_array_for_object(): void
{
    $test = new PropertyDefaultArrayForObject();
}
