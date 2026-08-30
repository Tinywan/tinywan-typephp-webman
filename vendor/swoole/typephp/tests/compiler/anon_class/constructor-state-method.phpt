--TEST--
anonymous class stores constructor state and uses it in methods
--FILE--
<?php

abstract class AnonStateVisitor
{
    abstract public function visit(string $name): string;
}

function main(): void
{
    $prefixes = ['node' => 'Node', 'leaf' => 'Leaf'];

    $visitor = new class($prefixes) extends AnonStateVisitor {
        public function __construct(private array $prefixes)
        {
        }

        public function visit(string $name): string
        {
            return ($this->prefixes[$name] ?? 'Unknown') . ':' . $name;
        }
    };

    var_dump($visitor->visit('node'));
    var_dump($visitor->visit('missing'));
}
?>
--EXPECT--
string(9) "Node:node"
string(15) "Unknown:missing"
