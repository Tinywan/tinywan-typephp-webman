<?php

#[Native]
class NativeProtectedMethodAccess
{
    protected function hidden(): void {}
}

function native_protected_method_access(): void
{
    $value = new NativeProtectedMethodAccess();
    $value->hidden();
}
