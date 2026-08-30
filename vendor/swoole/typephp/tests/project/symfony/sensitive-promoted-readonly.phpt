--TEST--
Symfony pattern: SensitiveParameter attribute on promoted readonly constructor parameter
--FILE--
<?php

class SymfonyLikeTransport
{
    public function __construct(
        #[SensitiveParameter] private readonly string $apiKey,
        private readonly ?string $region = null,
    ) {
    }

    public function describe(): string
    {
        return ($this->region ?? 'default').':'.strlen($this->apiKey);
    }
}

function main(): void
{
    $transport = new SymfonyLikeTransport('secret-token', region: 'eu');
    var_dump($transport->describe());

    $param = new ReflectionParameter([SymfonyLikeTransport::class, '__construct'], 'apiKey');
    var_dump($param->getAttributes()[0]->getName());

    $property = new ReflectionProperty(SymfonyLikeTransport::class, 'apiKey');
    var_dump($property->isPromoted());
    var_dump($property->isReadOnly());
}
?>
--EXPECT--
string(5) "eu:12"
string(18) "SensitiveParameter"
bool(true)
bool(true)
