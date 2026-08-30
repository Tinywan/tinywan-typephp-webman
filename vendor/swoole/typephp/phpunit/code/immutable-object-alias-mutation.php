<?php
class ImmutableAliasTarget
{
    public function mutate(): void {}
}

function immutableObjectAliasMutation(#[Immutable] ImmutableAliasTarget $target): void
{
    $alias = $target;
    $alias->mutate();
}
