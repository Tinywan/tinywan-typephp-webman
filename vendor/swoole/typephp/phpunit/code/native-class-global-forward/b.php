<?php

#[Native]
class NativeForwardGlobalValue
{
    public int $value = 42;
}

class NativeForwardGlobalFactory
{
    public static function create(): NativeForwardGlobalValue
    {
        return new NativeForwardGlobalValue();
    }

    public static function initialize(): void
    {
        global $nativeForwardGlobal;
        $local = self::create();
        $nativeForwardGlobal = $local;
    }
}

#[Native]
class NativeForwardGlobalCarrier
{
    public NativeForwardGlobalValue $value;

    public function create(): NativeForwardGlobalValue
    {
        return new NativeForwardGlobalValue();
    }
}

function initializeNativeForwardGlobalFromCarrier(NativeForwardGlobalCarrier $carrier): void
{
    global $nativeForwardGlobal;
    $nativeForwardGlobal = $carrier->create();
    $nativeForwardGlobal = $carrier->value;
}

#[Native]
class NativeForwardBase
{
    public int $value = 1;
}

#[Native]
class NativeForwardLeft extends NativeForwardBase
{
}

#[Native]
class NativeForwardRight extends NativeForwardBase
{
}

function initializeNativeForwardPolymorphic(bool $left): void
{
    global $nativeForwardPolymorphic;
    $nativeForwardPolymorphic = $left ? new NativeForwardLeft() : new NativeForwardRight();
}

function initializeNativeForwardCoalesced(): void
{
    global $nativeForwardCoalesced;
    $nativeForwardCoalesced ??= new NativeForwardGlobalValue();
}

function initializeNativeForwardGlobalsArray(): void
{
    $GLOBALS['nativeForwardGlobalsArray'] = new NativeForwardGlobalValue();
}

const NATIVE_FORWARD_CLOSURE_GLOBAL = 'nativeForwardClosureGlobal';

function initializeNativeForwardClosureGlobal(): void
{
    $initialize = static function (): void {
        $GLOBALS[NATIVE_FORWARD_CLOSURE_GLOBAL] = new NativeForwardGlobalValue();
    };
    $initialize();
}
