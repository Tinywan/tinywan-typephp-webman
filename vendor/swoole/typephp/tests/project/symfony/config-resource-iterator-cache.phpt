--TEST--
Symfony Config pattern: cache Traversable as array with iterator_to_array
--FILE--
<?php

final class ResourceCheckerCache
{
    public function __construct(private iterable $resourceCheckers)
    {
    }

    public function all(): array
    {
        if (!$this->resourceCheckers instanceof Traversable) {
            return $this->resourceCheckers;
        }

        return $this->resourceCheckers = iterator_to_array($this->resourceCheckers);
    }
}

function main(): void
{
    $cache = new ResourceCheckerCache(new ArrayIterator(['php' => true, 'yaml' => false]));
    var_dump($cache->all());
    var_dump($cache->all());
}
?>
--EXPECT--
array(2) {
  ["php"]=>
  bool(true)
  ["yaml"]=>
  bool(false)
}
array(2) {
  ["php"]=>
  bool(true)
  ["yaml"]=>
  bool(false)
}
