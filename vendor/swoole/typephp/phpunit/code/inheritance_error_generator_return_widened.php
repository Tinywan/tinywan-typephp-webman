<?php

interface GeneratorReturnContract
{
    public function values(): \Generator;
}

class GeneratorReturnImplementation implements GeneratorReturnContract
{
    public function values(): iterable
    {
        yield 1;
    }
}
