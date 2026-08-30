<?php

#[Printer(fields: ['id', 'name', 'tags', 'enabled', 'value'])]
#[Arrayable(fields: ['id', 'name', 'tags', 'enabled', 'value'])]
class PrinterArrayableFieldTypes
{
    private int $id = 1;
    private string $name = 'TypePHP';
    protected array $tags = ['a', 'b'];
    private bool $enabled = true;
    private mixed $value = null;
}
