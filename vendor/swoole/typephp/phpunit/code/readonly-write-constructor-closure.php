<?php
class ReadonlyConstructorClosure
{
    public readonly int $value;

    public function __construct()
    {
        $writer = function (): void {
            $this->value = 1;
        };
        $writer();
    }
}
