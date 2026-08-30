<?php

#[Native]
class NativeUnionMember
{
    public int $value = 1;
}

function invalidNativeUnion(NativeUnionMember|string $value): void
{
}
