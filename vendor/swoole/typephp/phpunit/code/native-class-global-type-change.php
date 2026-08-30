<?php

#[Native]
class NativeGlobalFirst {}

#[Native]
class NativeGlobalSecond {}

function setFirstNativeGlobal(): void
{
    global $nativeGlobal;
    $nativeGlobal = new NativeGlobalFirst();
}

function setSecondNativeGlobal(): void
{
    global $nativeGlobal;
    $nativeGlobal = new NativeGlobalSecond();
}

