<?php
class C extends ArrayObject
{
    public function offsetGet(int $key): string
    {
        return 'x';
    }
}

function main() {}
