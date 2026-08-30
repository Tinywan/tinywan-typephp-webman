--TEST--
Native class: self and parent signatures preserve typed pointer semantics
--FILE--
<?php

#[Native]
class NativeSignatureBase
{
    public ?self $peer;

    public function identity(self $value): self
    {
        return $value;
    }

    public function nullable(?self $value): ?self
    {
        return $value;
    }
}

#[Native]
class NativeSignatureChild extends NativeSignatureBase
{
    public ?parent $parentPeer;

    public function childIdentity(self $value): self
    {
        return $value;
    }

    public function parentIdentity(parent $value): parent
    {
        return $value;
    }
}

function main(): void
{
    $base = new NativeSignatureBase();
    $child = new NativeSignatureChild();
    $base->peer = $base;
    $child->parentPeer = $child;

    var_dump(
        $base->identity($base) === $base,
        $base->nullable(null) === null,
        $child->childIdentity($child) === $child,
        $child->parentIdentity($child) === $child,
        $base->peer === $base,
        $child->parentPeer === $child,
    );
}

?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
