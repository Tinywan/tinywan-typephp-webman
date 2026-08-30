--TEST--
Symfony Workflow style array dim coalesce assignment followed by assign-op
--FILE--
<?php
class SymfonyWorkflowMarkingCase
{
    private array $places = [];

    public function __construct(array $representation = [])
    {
        foreach ($representation as $place => $nbToken) {
            $this->mark($place, $nbToken);
        }
    }

    public function mark(string $place, int $nbToken = 1): void
    {
        if ($nbToken < 1) {
            throw new InvalidArgumentException(sprintf('The number of tokens must be greater than 0, "%s" given.', $nbToken));
        }

        $this->places[$place] ??= 0;
        $this->places[$place] += $nbToken;
    }

    public function getPlaces(): array
    {
        return $this->places;
    }
}

function main(): void
{
    $marking = new SymfonyWorkflowMarkingCase(['draft' => 1]);
    $marking->mark('draft', 2);
    $marking->mark('review', 3);

    var_dump($marking->getPlaces());

    try {
        $marking->mark('invalid', 0);
    } catch (InvalidArgumentException $e) {
        echo $e->getMessage(), "\n";
    }
}
?>
--EXPECT--
array(2) {
  ["draft"]=>
  int(3)
  ["review"]=>
  int(3)
}
The number of tokens must be greater than 0, "0" given.
