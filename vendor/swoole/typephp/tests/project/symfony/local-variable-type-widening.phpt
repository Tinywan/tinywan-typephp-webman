--TEST--
Symfony pattern: local variable reassigns from string to array in constructor
--SKIPIF--
<?php
exit('skip Known AOT compile bug: local variable cannot widen from string to array');
?>
--FILE--
<?php

class SymfonyLikeCommandNameNormalizer
{
    public function __construct(public string $name, array $aliases = [])
    {
        $name = explode('|', $name);
        $name = array_merge($name, $aliases);
        $this->name = implode('|', $name);
    }
}

function main(): void
{
    var_dump((new SymfonyLikeCommandNameNormalizer('cache:clear', ['cache:clean']))->name);
}
?>
--EXPECT--
string(23) "cache:clear|cache:clean"
