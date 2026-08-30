--TEST--
Native class: strict identity never coerces raw pointers to PHP values
--FILE--
<?php

#[Native]
class NativeStrictIdentity {}

#[Native]
class NativeStrictIdentityBase {}

#[Native]
class NativeStrictIdentityChild extends NativeStrictIdentityBase {}

function nativeIdentityOperand(NativeStrictIdentity $value): NativeStrictIdentity
{
    echo "native\n";
    return $value;
}

function zendIdentityOperand(): bool
{
    echo "zend\n";
    return true;
}

function makeIdentityOperand(): NativeStrictIdentity
{
    return new NativeStrictIdentity();
}

function makeIdentityOperandAfterPressure(): NativeStrictIdentity
{
    for ($i = 0; $i < 300000; $i++) {
        $filler = new NativeStrictIdentity();
    }
    return new NativeStrictIdentity();
}

function identityAsBase(NativeStrictIdentityBase $value): NativeStrictIdentityBase
{
    return $value;
}

function main(): void
{
    $value = new NativeStrictIdentity();
    $alias = $value;
    $other = new NativeStrictIdentity();

    var_dump($value === $alias);
    var_dump($value === $other);
    var_dump($value === true);
    var_dump($value !== true);
    var_dump(nativeIdentityOperand($value) === zendIdentityOperand());
    var_dump(zendIdentityOperand() === nativeIdentityOperand($value));
    var_dump(makeIdentityOperand() === makeIdentityOperandAfterPressure());
    $child = new NativeStrictIdentityChild();
    $base = identityAsBase($child);
    var_dump($base === $child, $child === $base);
}

?>
--EXPECT--
bool(true)
bool(false)
bool(false)
bool(true)
native
zend
bool(false)
zend
native
bool(false)
bool(false)
bool(true)
bool(true)
