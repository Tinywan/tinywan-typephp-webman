<?php

interface ITypeSelf
{
}

class DemoSelfIntersection implements ITypeSelf
{
    public function run(self&ITypeSelf $value): void {}
}

function main() {}
