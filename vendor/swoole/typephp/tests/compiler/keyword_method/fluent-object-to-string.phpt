--TEST--
toString keyword converts fluent object results through __toString
--FILE--
<?php

final class FluentText
{
    public function __construct(private string $value)
    {
    }

    public function append(string ...$values): self
    {
        $this->value .= implode('', $values);
        return $this;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

function main(): void
{
    $start = new FluentText('start');
    $end = new FluentText('end');
    echo $start->append(':', $end->toString())->toString(), "\n";
}
?>
--EXPECT--
start:end
