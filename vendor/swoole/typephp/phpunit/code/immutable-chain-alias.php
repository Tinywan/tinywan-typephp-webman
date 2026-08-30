<?php

class ImmutableChainAliasTarget
{
    public function mutate(): void {}
}

function immutableChainAlias(#[Immutable] ImmutableChainAliasTarget $target): void
{
    $first = $second = $target;
    $first->mutate();
}
