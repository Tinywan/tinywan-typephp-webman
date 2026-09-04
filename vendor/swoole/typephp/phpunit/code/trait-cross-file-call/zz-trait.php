<?php

namespace CrossFileTrait;

trait AttributeLookup
{
    public function getAttribute(string $class = 'default'): string
    {
        return 'attribute:' . $class;
    }
}
