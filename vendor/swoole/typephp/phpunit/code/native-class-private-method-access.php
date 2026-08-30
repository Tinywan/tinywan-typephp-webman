<?php

#[Native]
class NativePrivateMethodAccess
{
    private function hidden(): void {}
}

function native_private_method_access(): void
{
    $value = new NativePrivateMethodAccess();
    $value->hidden();
}
