<?php

namespace CrossFileTrait;

class ClassB
{
    use AttributeLookup;

    public function __call(string $name, array $arguments): mixed
    {
        return 'magic:' . $name;
    }
}

class ClassC
{
    use AttributeLookup;
}
