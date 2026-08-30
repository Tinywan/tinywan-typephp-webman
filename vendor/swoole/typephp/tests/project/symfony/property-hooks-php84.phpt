--TEST--
Symfony pattern: PHP 8.4 property hooks
--SKIPIF--
<?php
exit('skip PHP 8.4 property hooks are not supported by the AOT compiler');
?>
--FILE--
<?php

class SymfonyLikeHookedService
{
    private array $services = [
        'dependency' => 'value',
    ];

    public string $dependency {
        get => $this->services['dependency'];
    }
}

function main(): void
{
    $service = new SymfonyLikeHookedService();
    var_dump($service->dependency);
}
?>
--EXPECT--
string(5) "value"
