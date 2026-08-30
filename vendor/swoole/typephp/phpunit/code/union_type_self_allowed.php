<?php

class DemoSelfUnion
{
    public function run(self|string $value): void {}
}

function main() {}
