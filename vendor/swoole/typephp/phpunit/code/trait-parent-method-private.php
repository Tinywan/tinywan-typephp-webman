<?php

trait SecretTrait
{
    public function reveal(): string
    {
        return parent::secret();
    }
}

class BaseSecret
{
    private function secret(): string
    {
        return 'secret';
    }
}

class ChildSecret extends BaseSecret
{
    use SecretTrait;
}

function main(): void
{
    $c = new ChildSecret();
    var_dump($c->reveal());
}
