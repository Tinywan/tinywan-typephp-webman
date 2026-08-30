--TEST--
Symfony pattern: nested array_merge unpack passed to method variadic
--FILE--
<?php

final class Envelope
{
    public function __construct(private array $groups)
    {
    }

    public function all(): array
    {
        return $this->groups;
    }

    public function with(string ...$stamps): self
    {
        $groups = $this->groups;
        $groups['added'] = $stamps;

        return new self($groups);
    }

    public function flat(): array
    {
        return array_merge(...array_values($this->groups));
    }
}

function mergeStamps(Envelope $decodedEnvelope, Envelope $failedEnvelope): Envelope
{
    return $decodedEnvelope->with(...array_merge(...array_values($failedEnvelope->all())));
}

function main(): void
{
    $failed = new Envelope([
        'first' => ['red', 'blue'],
        'second' => ['green'],
    ]);
    $decoded = new Envelope([
        'decoded' => ['base'],
    ]);

    var_dump(mergeStamps($decoded, $failed)->flat());
    var_dump(array_unique(array_merge(...array_values([
        'bus-a' => ['json', 'php'],
        'bus-b' => ['json', 'xml'],
    ]))));
}
?>
--EXPECT--
array(4) {
  [0]=>
  string(4) "base"
  [1]=>
  string(3) "red"
  [2]=>
  string(4) "blue"
  [3]=>
  string(5) "green"
}
array(3) {
  [0]=>
  string(4) "json"
  [1]=>
  string(3) "php"
  [3]=>
  string(3) "xml"
}
