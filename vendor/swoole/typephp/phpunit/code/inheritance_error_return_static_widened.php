<?php

class StaticReturnParent
{
    public function value(): static
    {
        return $this;
    }
}

class StaticReturnChild extends StaticReturnParent
{
    public function value(): self
    {
        return $this;
    }
}
