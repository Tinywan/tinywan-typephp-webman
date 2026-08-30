--TEST--
Symfony EventDispatcher style wrapped listener with first-class callable and cached info
--FILE--
<?php
interface SymfonyPriorityDispatcher
{
    public function getListenerPriority(string $eventName, callable|array $listener): int;
}

class SymfonyWrappedListenerCase
{
    private callable|array $listener;
    private ?Closure $optimizedListener;
    private ?int $priority = null;
    private mixed $stub = null;
    private string $pretty;
    private string $name;
    public bool $called = false;

    public function __construct(
        callable|array $listener,
        ?string $name,
        private ?SymfonyPriorityDispatcher $dispatcher = null,
    ) {
        $this->listener = $listener;
        $this->optimizedListener = $listener instanceof Closure ? $listener : (is_callable($listener) ? $listener(...) : null);

        if (is_array($listener)) {
            $this->name = get_debug_type($listener[0]);
            $this->pretty = $this->name . '::' . $listener[1];
        } elseif ($listener instanceof Closure) {
            $this->pretty = $this->name = 'closure';
        } elseif (is_string($listener)) {
            $this->pretty = $this->name = $listener;
        } else {
            $this->name = get_debug_type($listener);
            $this->pretty = $this->name . '::__invoke';
        }

        if (null !== $name) {
            $this->name = $name;
        }
    }

    public function getInfo(string $eventName): array
    {
        $this->stub ??= $this->pretty . '()';

        return [
            'event' => $eventName,
            'priority' => $this->priority ??= $this->dispatcher?->getListenerPriority($eventName, $this->listener),
            'pretty' => $this->pretty,
            'stub' => $this->stub,
        ];
    }

    public function __invoke(object $event, string $eventName, SymfonyPriorityDispatcher $dispatcher): void
    {
        $this->called = true;
        $this->priority ??= $dispatcher->getListenerPriority($eventName, $this->listener);

        ($this->optimizedListener ?? $this->listener)($event, $eventName, $dispatcher);
    }
}

class SymfonyWrappedListenerTarget
{
    public array $events = [];

    public function onEvent(object $event, string $eventName): void
    {
        $this->events[] = $eventName . ':' . $event->name;
    }
}

class SymfonyWrappedListenerDispatcher implements SymfonyPriorityDispatcher
{
    public int $calls = 0;

    public function getListenerPriority(string $eventName, callable|array $listener): int
    {
        $this->calls++;

        return 32;
    }
}

function main(): void
{
    $target = new SymfonyWrappedListenerTarget();
    $dispatcher = new SymfonyWrappedListenerDispatcher();
    $wrapped = new SymfonyWrappedListenerCase([$target, 'onEvent'], null, $dispatcher);

    var_dump($wrapped->getInfo('kernel.request'));
    $wrapped((object) ['name' => 'request'], 'kernel.request', $dispatcher);
    var_dump($target->events);
    var_dump($wrapped->called);
    var_dump($dispatcher->calls);
}
?>
--EXPECTF--
array(4) {
  ["event"]=>
  string(14) "kernel.request"
  ["priority"]=>
  int(32)
  ["pretty"]=>
  string(%d) "%s::onEvent"
  ["stub"]=>
  string(%d) "%s::onEvent()"
}
array(1) {
  [0]=>
  string(22) "kernel.request:request"
}
bool(true)
int(1)
