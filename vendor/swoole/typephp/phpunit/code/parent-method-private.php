<?php

class Base
{
    private function secret(): string
    {
        return 'secret';
    }
}

class Child extends Base
{
    public function reveal(): string
    {
        return parent::secret();
    }
}

function main(): void
{
    $c = new Child();
    var_dump($c->reveal());
}
