<?php
trait JobTrait
{
    abstract private function run(): void;

    public function go(): void
    {
        $this->run();
    }
}

class Job
{
    use JobTrait;

    private function run(): void {}
}

function main() {}
