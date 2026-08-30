<?php

#[Native]
class NativePromotedPrivateSetExternalWrite
{
    public function __construct(
        public private(set) int $value,
    ) {
    }
}

function main(): void
{
    $object = new NativePromotedPrivateSetExternalWrite(1);
    $object->value = 2;
}
