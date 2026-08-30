--TEST--
Symfony pattern: __serialize and __unserialize restore private state
--FILE--
<?php

final class SerializableState
{
    private string $resource;
    private ?float $expiresAt = null;
    private array $state = [];

    public function __construct(string $resource)
    {
        $this->resource = $resource;
    }

    public function setState(string $store, mixed $value): void
    {
        $this->state[$store] = $value;
    }

    public function __serialize(): array
    {
        return [
            'resource' => $this->resource,
            'expiresAt' => $this->expiresAt,
            'state' => $this->state,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->resource = $data['resource'];
        $this->expiresAt = $data['expiresAt'] ?? null;
        $this->state = $data['state'] ?? [];
    }

    public function describe(): string
    {
        return $this->resource.':'.implode(',', array_keys($this->state));
    }
}

function main(): void
{
    $state = new SerializableState('lock-key');
    $state->setState('redis', ['token' => 'abc']);
    $state->setState('pdo', ['token' => 'def']);

    $copy = unserialize(serialize($state));

    var_dump($copy instanceof SerializableState);
    var_dump($copy->describe());
}
?>
--EXPECT--
bool(true)
string(18) "lock-key:redis,pdo"
