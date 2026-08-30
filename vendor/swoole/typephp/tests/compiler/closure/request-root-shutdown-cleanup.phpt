--TEST--
request shutdown releases compiled globals, static properties and closure cycles
--FILE--
<?php

class RequestRootNode
{
    public ?RequestRootNode $next = null;
}

class RequestRootCache
{
    public static array $objects = [];
    public static ?Closure $callback = null;
}

function main(): void
{
    global $requestRootObjects;

    $first = new RequestRootNode();
    $second = new RequestRootNode();
    $first->next = $second;
    $second->next = $first;

    $requestRootObjects = [$first, $second];
    RequestRootCache::$objects = [$first, $second];

    $callback = null;
    $callback = static function () use (&$callback): void {
    };
    RequestRootCache::$callback = $callback;

    echo "done\n";
}
?>
--EXPECT--
done
