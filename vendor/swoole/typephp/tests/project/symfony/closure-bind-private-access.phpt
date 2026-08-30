--TEST--
Symfony pattern: cache Closure::bind private property accessor
--XFAIL--
Known AOT bug: Closure::bind() on a static closure can be treated as unbinding $this from a method closure.
--FILE--
<?php

class SymfonyLikeCacheItem
{
    private mixed $value = null;

    public function expose(): mixed
    {
        return $this->value;
    }
}

class SymfonyLikeCacheAdapter
{
    private static ?Closure $setValue = null;

    public function set(SymfonyLikeCacheItem $item, mixed $value): void
    {
        $setValue = self::$setValue ??= Closure::bind(
            static function (SymfonyLikeCacheItem $item, mixed $value): void {
                $item->value = $value;
            },
            null,
            SymfonyLikeCacheItem::class
        );

        $setValue($item, $value);
    }
}

function main(): void
{
    $item = new SymfonyLikeCacheItem();
    $adapter = new SymfonyLikeCacheAdapter();

    $adapter->set($item, 'cached');
    var_dump($item->expose());
}
?>
--EXPECT--
string(6) "cached"
