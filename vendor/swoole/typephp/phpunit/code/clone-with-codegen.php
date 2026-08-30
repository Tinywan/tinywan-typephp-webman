<?php

class CloneWithCodegen
{
    private int $value = 1;

    public function copy(): self
    {
        return clone($this, ['value' => 2]);
    }
}

function clone_with_global(CloneWithCodegen $value): CloneWithCodegen
{
    return clone($value, []);
}
