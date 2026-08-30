--TEST--
Trait method calling protected parent::method() of the composing class
--FILE--
<?php

trait GreetTrait
{
    public function greet(string $name): string
    {
        return parent::greet($name) . ' [via trait]';
    }
}

class BaseGreeter
{
    protected function greet(string $name): string
    {
        return 'Hello ' . $name;
    }
}

class ChildGreeter extends BaseGreeter
{
    use GreetTrait;
}

function main(): void
{
    $g = new ChildGreeter();
    var_dump($g->greet('World'));
}
?>
--EXPECT--
string(23) "Hello World [via trait]"
