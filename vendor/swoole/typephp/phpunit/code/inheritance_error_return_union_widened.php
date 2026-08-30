<?php

interface UnionReturnParent
{
    public function value(): int|string;
}

class UnionReturnChild implements UnionReturnParent
{
    public function value(): bool
    {
        return true;
    }
}
