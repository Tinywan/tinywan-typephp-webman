--TEST--
Symfony Serializer style array_map(strval(...)) with Stringable values
--FILE--
<?php
class SymfonyExpectedTypeName
{
    public function __construct(private string $name)
    {
    }

    public function __toString(): string
    {
        return 'type:' . $this->name;
    }
}

class SymfonyNotNormalizableValueCase extends RuntimeException
{
    private ?array $expectedTypes;

    public function __construct(?array $expectedTypes = null)
    {
        parent::__construct('not normalizable');

        $this->expectedTypes = $expectedTypes ? array_map(strval(...), $expectedTypes) : $expectedTypes;
    }

    public function getExpectedTypes(): ?array
    {
        return $this->expectedTypes;
    }
}

function main(): void
{
    $exception = new SymfonyNotNormalizableValueCase([
        'int',
        new SymfonyExpectedTypeName('uuid'),
        'array',
    ]);

    var_dump($exception->getExpectedTypes());

    $empty = new SymfonyNotNormalizableValueCase();
    var_dump($empty->getExpectedTypes());
}
?>
--EXPECT--
array(3) {
  [0]=>
  string(3) "int"
  [1]=>
  string(9) "type:uuid"
  [2]=>
  string(5) "array"
}
NULL
