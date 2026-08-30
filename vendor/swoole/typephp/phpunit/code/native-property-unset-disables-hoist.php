<?php

use native_types;

class NativePropertyUnsetDisablesHoist
{
    public int $value = 7;

    public function run(): void
    {
        var_dump($this->value);
        unset($this->value);
        var_dump($this->value);
        $this->value = 11;
        var_dump($this->value);
    }
}

function main(): void
{
    (new NativePropertyUnsetDisablesHoist())->run();
}
