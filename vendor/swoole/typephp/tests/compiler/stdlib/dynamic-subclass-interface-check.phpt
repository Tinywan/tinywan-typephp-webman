--TEST--
dynamic is_subclass_of and class_implements checks
--FILE--
<?php

interface DynamicContract {}
class DynamicBase {}
class DynamicImpl extends DynamicBase implements DynamicContract {}

function check_subclass(string|object $value, string $class): bool
{
    return is_subclass_of($value, $class);
}

function check_implements(string|object $value, string $interface): bool
{
    return in_array($interface, class_implements($value));
}

function main(): void
{
    var_dump(check_subclass(DynamicImpl::class, DynamicBase::class));
    var_dump(check_subclass(new DynamicImpl(), DynamicBase::class));
    var_dump(check_subclass(DynamicBase::class, DynamicImpl::class));

    var_dump(check_implements(DynamicImpl::class, DynamicContract::class));
    var_dump(check_implements(new DynamicImpl(), DynamicContract::class));
    var_dump(check_implements(DynamicBase::class, DynamicContract::class));
}
?>
--EXPECT--
bool(true)
bool(true)
bool(false)
bool(true)
bool(true)
bool(false)
