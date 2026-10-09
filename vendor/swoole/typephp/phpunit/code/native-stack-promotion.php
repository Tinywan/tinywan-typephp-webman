<?php

#[Native]
class NativeStackPromotionFixture
{
    public int $value = 0;

    public function __construct(int $value)
    {
        $this->value = $value;
    }

    public function read(): int
    {
        return $this->value;
    }
}

function promotedNativeObject(): int
{
    $value = new NativeStackPromotionFixture(42);
    return $value->read();
}

function escapedNativeObject(): NativeStackPromotionFixture
{
    $value = new NativeStackPromotionFixture(42);
    return $value;
}

#[Native]
class NativeStackFinalizerFixture
{
    public int $value = 42;

    public function __destruct()
    {
    }
}

function finalizedNativeObject(): int
{
    $value = new NativeStackFinalizerFixture();
    return $value->value;
}

#[Native]
class NativeStackPromotionBase
{
    public int $value = 42;

    private function readPrivate(): int
    {
        return $this->value;
    }

    public function readFromBase(): int
    {
        return $this->readPrivate();
    }
}

#[Native]
class NativeStackPromotionChild extends NativeStackPromotionBase
{
    public function readFromBase(): int
    {
        return parent::readFromBase() + 1;
    }
}

function inheritedNativeObject(): int
{
    $value = new NativeStackPromotionChild();
    return $value->readFromBase();
}

#[Native]
final class NativeCompactScalarFixture
{
    public int32 $number = 0;
    public int8 $tag = 0;
    public uint16 $flags = 0;
    public float32 $ratio = 0.0;
}
