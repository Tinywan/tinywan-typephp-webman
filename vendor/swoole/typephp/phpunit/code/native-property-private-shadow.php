<?php

#[Native]
class NativePrivateShadowParent
{
    private int $value = 1;
}

#[Native]
class NativePrivateShadowChild extends NativePrivateShadowParent
{
    private int $value = 2;
}
