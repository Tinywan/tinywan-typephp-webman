<?php

class BaseParentUnion
{
}

class DemoParentUnion extends BaseParentUnion
{
    public function run(parent|string $value): void {}
}

function main() {}
