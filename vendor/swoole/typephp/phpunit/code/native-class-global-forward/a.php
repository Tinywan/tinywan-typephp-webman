<?php

function readNativeForwardGlobal(): int
{
    global $nativeForwardGlobal;
    return $nativeForwardGlobal->value;
}

function readNativeForwardPolymorphic(): int
{
    global $nativeForwardPolymorphic;
    return $nativeForwardPolymorphic->value;
}

function readNativeForwardCoalesced(): int
{
    global $nativeForwardCoalesced;
    return $nativeForwardCoalesced->value;
}

function readNativeForwardGlobalsArray(): int
{
    return $GLOBALS['nativeForwardGlobalsArray']->value;
}

function readNativeForwardClosureGlobal(): int
{
    global $nativeForwardClosureGlobal;
    return $nativeForwardClosureGlobal->value;
}
