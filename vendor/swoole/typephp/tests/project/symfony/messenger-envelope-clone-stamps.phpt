--TEST--
Symfony style Envelope clone with variadic stamps and dynamic stamp removal
--FILE--
<?php
interface StampInterface {}
interface RemovableStampInterface extends StampInterface {}

class FirstStamp implements RemovableStampInterface
{
    public function __construct(public string $name) {}
}

class SecondStamp implements StampInterface
{
    public function __construct(public string $name) {}
}

final class Envelope
{
    private array $stamps = [];

    public function __construct(private object $message, array $stamps = [])
    {
        foreach ($stamps as $stamp) {
            $this->stamps[$stamp::class][] = $stamp;
        }
    }

    public static function wrap(object $message, array $stamps = []): self
    {
        $envelope = $message instanceof self ? $message : new self($message);

        return $envelope->with(...$stamps);
    }

    public function with(StampInterface ...$stamps): static
    {
        $cloned = clone $this;

        foreach ($stamps as $stamp) {
            $cloned->stamps[$stamp::class][] = $stamp;
        }

        return $cloned;
    }

    public function withoutStampsOfType(string $type): self
    {
        $cloned = clone $this;

        foreach ($cloned->stamps as $class => $stamps) {
            if ($class === $type || is_subclass_of($class, $type)) {
                unset($cloned->stamps[$class]);
            }
        }

        return $cloned;
    }

    public function all(?string $stampFqcn = null): array
    {
        if (null !== $stampFqcn) {
            return $this->stamps[$stampFqcn] ?? [];
        }

        return $this->stamps;
    }
}

function main(): void
{
    $envelope = Envelope::wrap(new stdClass(), [new FirstStamp('a'), new SecondStamp('b')]);
    $filtered = $envelope->withoutStampsOfType(RemovableStampInterface::class);
    var_dump(array_keys($envelope->all()));
    var_dump(array_keys($filtered->all()));
    var_dump($filtered->all(FirstStamp::class));
}
?>
--EXPECT--
array(2) {
  [0]=>
  string(10) "FirstStamp"
  [1]=>
  string(11) "SecondStamp"
}
array(1) {
  [0]=>
  string(11) "SecondStamp"
}
array(0) {
}
