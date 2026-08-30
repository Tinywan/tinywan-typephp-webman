<?php

#[Printer]
class PrinterGeneratedMethodConflict
{
    public string $name = 'TypePHP';

    public function __toString(): string
    {
        return $this->name;
    }
}
