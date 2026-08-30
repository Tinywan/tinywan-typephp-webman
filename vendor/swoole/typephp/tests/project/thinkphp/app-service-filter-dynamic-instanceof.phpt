--TEST--
ThinkPHP App pattern: array_filter with dynamic instanceof service lookup
--FILE--
<?php

class ThinkServiceBase
{
}

class ThinkLoggerService extends ThinkServiceBase
{
}

class ThinkCacheService extends ThinkServiceBase
{
}

class ThinkAppServiceLike
{
    private array $services = [];

    public function register(object $service): void
    {
        $this->services[] = $service;
    }

    public function getService(object|string $service): ?object
    {
        $name = is_string($service) ? $service : $service::class;
        return array_values(array_filter($this->services, function ($value) use ($name) {
            return $value instanceof $name;
        }, ARRAY_FILTER_USE_BOTH))[0] ?? null;
    }

    public function boot(): array
    {
        $booted = [];
        array_walk($this->services, function ($service) use (&$booted) {
            $booted[] = $service::class;
        });
        return $booted;
    }
}

function main(): void
{
    $app = new ThinkAppServiceLike();
    $app->register(new ThinkLoggerService());
    $app->register(new ThinkCacheService());

    var_dump($app->getService(ThinkCacheService::class)::class);
    var_dump($app->getService(new ThinkLoggerService())::class);
    var_dump($app->getService(DateTimeImmutable::class));
    var_dump($app->boot());
}
?>
--EXPECT--
string(17) "ThinkCacheService"
string(18) "ThinkLoggerService"
NULL
array(2) {
  [0]=>
  string(18) "ThinkLoggerService"
  [1]=>
  string(17) "ThinkCacheService"
}
