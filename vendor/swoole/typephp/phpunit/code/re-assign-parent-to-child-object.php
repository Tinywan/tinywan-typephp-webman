<?php

class TypedObjectAssignBase
{
}

class TypedObjectAssignChild extends TypedObjectAssignBase
{
}

function main(): void
{
    $child = new TypedObjectAssignChild();
    $child = new TypedObjectAssignBase();
}
