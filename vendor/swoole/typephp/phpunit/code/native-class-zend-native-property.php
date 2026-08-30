<?php

#[Native]
class NativePropertyType
{
    public int $value = 1;
}

class ZendObjectWithNativeProperty
{
    public NativePropertyType $value;
}
