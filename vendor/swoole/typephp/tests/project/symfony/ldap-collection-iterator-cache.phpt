--TEST--
Symfony Ldap pattern: iterator_to_array without keys cached by coalesce assignment
--FILE--
<?php
class SymfonyLdapEntryCollection implements IteratorAggregate
{
    private ?array $entries = null;
    public int $iterations = 0;

    public function __construct(private array $source)
    {
    }

    public function getIterator(): Traversable
    {
        ++$this->iterations;

        return new ArrayIterator($this->source);
    }

    public function toArray(): array
    {
        return $this->entries ??= iterator_to_array($this->getIterator(), false);
    }
}

function main(): void
{
    $collection = new SymfonyLdapEntryCollection([
        'uid' => ['alice'],
        'mail' => ['alice@example.com'],
    ]);

    var_dump($collection->toArray());
    var_dump($collection->toArray());
    var_dump($collection->iterations);
}
?>
--EXPECT--
array(2) {
  [0]=>
  array(1) {
    [0]=>
    string(5) "alice"
  }
  [1]=>
  array(1) {
    [0]=>
    string(17) "alice@example.com"
  }
}
array(2) {
  [0]=>
  array(1) {
    [0]=>
    string(5) "alice"
  }
  [1]=>
  array(1) {
    [0]=>
    string(17) "alice@example.com"
  }
}
int(1)
