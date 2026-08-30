--TEST--
Symfony pattern: Closure::bind returns reference to private property
--SKIPIF--
<?php
exit("skip: returning by reference from a closure is not supported in AOT");
?>
--FILE--
<?php

final class ReferenceConfigurator
{
    private array $instanceof = [
        'base' => 'service',
    ];

    public function getReferenceAccessor(): Closure
    {
        return Closure::bind(fn &() => $this->instanceof, $this, self::class);
    }

    public function dump(): void
    {
        foreach ($this->instanceof as $key => $value) {
            var_dump($key.':'.$value);
        }
    }
}

function main(): void
{
    $configurator = new ReferenceConfigurator();
    $accessor = $configurator->getReferenceAccessor();
    $instanceof = &$accessor();

    $instanceof['extra'] = 'listener';
    $instanceof['base'] = strtoupper($instanceof['base']);

    $configurator->dump();
}
?>
--EXPECT--
string(12) "base:SERVICE"
string(14) "extra:listener"
