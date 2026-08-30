--TEST--
ThinkPHP Event pattern: Reflection observer registration and dynamic dispatch
--FILE--
<?php

class ThinkEventLike
{
    private array $listener = [];

    public function listen(string $event, callable $listener): void
    {
        $this->listener[$event][] = $listener;
    }

    public function observe(object $observer, string $prefix = ''): static
    {
        $reflect = new ReflectionClass($observer);
        $methods = $reflect->getMethods(ReflectionMethod::IS_PUBLIC);

        if (empty($prefix) && $reflect->hasProperty('eventPrefix')) {
            $reflectProperty = $reflect->getProperty('eventPrefix');
            $prefix = $reflectProperty->getValue($observer);
        }

        foreach ($methods as $method) {
            $name = $method->getName();
            if (str_starts_with($name, 'on')) {
                $this->listen($prefix . substr($name, 2), [$observer, $name]);
            }
        }

        return $this;
    }

    public function trigger(string $event, mixed $params = null): array
    {
        $result = [];
        foreach ($this->listener[$event] ?? [] as $key => $listener) {
            $result[$key] = call_user_func_array($listener, [$params]);
        }
        return $result;
    }
}

class ThinkOrderObserver
{
    public string $eventPrefix = 'Order.';

    public function onCreated(array $payload): string
    {
        return 'created:' . $payload['id'];
    }

    public function onPaid(array $payload): string
    {
        return 'paid:' . $payload['id'];
    }
}

function main(): void
{
    $event = new ThinkEventLike();
    $event->observe(new ThinkOrderObserver());

    var_dump($event->trigger('Order.Created', ['id' => 42]));
    var_dump($event->trigger('Order.Paid', ['id' => 42]));
}
?>
--EXPECT--
array(1) {
  [0]=>
  string(10) "created:42"
}
array(1) {
  [0]=>
  string(7) "paid:42"
}
