<?php

namespace TypePhp\Build;

final class CompileOptions extends CommandOptions
{
    public function with(string $name, mixed $value): self
    {
        $values = $this->values;
        $values[$name] = $value;
        return new self($values);
    }
}
