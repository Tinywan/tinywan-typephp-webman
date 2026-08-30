<?php

interface ITypeStatic
{
}

class DemoStaticIntersection implements ITypeStatic
{
    public function run(): static&ITypeStatic
    {
        return $this;
    }
}

function main() {}
