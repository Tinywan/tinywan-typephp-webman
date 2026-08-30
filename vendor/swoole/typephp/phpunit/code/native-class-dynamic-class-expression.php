<?php

#[Native]
class NativeDynamicClassExpressionTarget
{
}

function native_dynamic_class_expression(): void
{
    new (NativeDynamicClassExpressionTarget::class)();
}
