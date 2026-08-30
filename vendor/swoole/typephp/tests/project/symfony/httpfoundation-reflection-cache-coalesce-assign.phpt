--TEST--
Symfony HttpFoundation style static reflection cache with coalesce assignment
--FILE--
<?php
class SymfonyReflectionCacheResponse
{
    private string $statusText = 'initial';

    public function getStatusText(): string
    {
        return $this->statusText;
    }

    public static function setProperty(self $response, string $name, mixed $value): void
    {
        static $cache;

        $property = $cache[$name] ??= new ReflectionProperty(self::class, $name);
        $property->setValue($response, $value);
    }
}

function main(): void
{
    $response = new SymfonyReflectionCacheResponse();

    SymfonyReflectionCacheResponse::setProperty($response, 'statusText', 'created');
    var_dump($response->getStatusText());

    SymfonyReflectionCacheResponse::setProperty($response, 'statusText', 'accepted');
    var_dump($response->getStatusText());
}
?>
--EXPECT--
string(7) "created"
string(8) "accepted"
