<?php

class GeneratedMethodFinalConflictParent
{
    final public function withName(string $name): static
    {
        return $this;
    }
}

class GeneratedMethodFinalConflictChild extends GeneratedMethodFinalConflictParent
{
    #[With]
    private string $name;
}
