<?php

function call_cache_target(int $value): int
{
    return $value + 1;
}

function call_cache_sites(mixed $callback, object $object, mixed $method): array
{
    return [$callback(1), $object->$method(2)];
}

class CallCacheScopedTarget
{
    private function hidden(): int
    {
        return 3;
    }

    public function invoke(mixed $method): int
    {
        return $this->$method();
    }
}

function main(): void
{
}
