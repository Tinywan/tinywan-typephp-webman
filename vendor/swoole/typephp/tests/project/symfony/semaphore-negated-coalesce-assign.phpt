--TEST--
Symfony Semaphore pattern: negated coalesce assignment in condition
--FILE--
<?php
class SymfonySemaphoreLike
{
    public function __construct(private ?float $ttlInSecond = null)
    {
    }

    public function refresh(?float $ttlInSecond = null): string
    {
        if (!$ttlInSecond ??= $this->ttlInSecond) {
            return 'missing';
        }

        return 'ttl='.$ttlInSecond;
    }
}

function main(): void
{
    var_dump((new SymfonySemaphoreLike())->refresh());
    var_dump((new SymfonySemaphoreLike(2.5))->refresh());
    var_dump((new SymfonySemaphoreLike(2.5))->refresh(1.25));
    var_dump((new SymfonySemaphoreLike(2.5))->refresh(0.0));
}
?>
--EXPECT--
string(7) "missing"
string(7) "ttl=2.5"
string(8) "ttl=1.25"
string(7) "missing"
