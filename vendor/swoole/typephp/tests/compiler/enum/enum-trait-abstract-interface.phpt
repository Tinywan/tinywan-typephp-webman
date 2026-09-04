--TEST--
An enum may satisfy Trait abstract methods and implement interfaces
--FILE--
<?php

interface Labeled
{
    public function label(): string;
}

trait RequiresLabel
{
    abstract public function label(): string;

    public function description(): string
    {
        return 'case=' . $this->label();
    }
}

enum Suit implements Labeled
{
    use RequiresLabel;

    case Hearts;

    public function label(): string
    {
        return $this->name;
    }
}

function main(): void
{
    var_dump(Suit::Hearts instanceof Labeled);
    var_dump(Suit::Hearts->label());
    var_dump(Suit::Hearts->description());
}
?>
--EXPECT--
bool(true)
string(6) "Hearts"
string(11) "case=Hearts"
