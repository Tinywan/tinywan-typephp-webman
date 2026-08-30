--TEST--
Symfony HttpClient style SplObjectStorage promise pool with clone options
--ENV--
USE_ZEND_ALLOC=0
--FILE--
<?php
class SymfonyHttpClientPoolCase
{
    private ?SplObjectStorage $promisePool;
    private bool $autoUpgradeHttpVersion = true;
    private array $options = [];

    public function __construct()
    {
        $this->promisePool = class_exists(SplObjectStorage::class) ? new SplObjectStorage() : null;
    }

    public function withOptions(array $options): static
    {
        $clone = clone $this;
        if (array_key_exists('auto_upgrade_http_version', $options)) {
            $clone->autoUpgradeHttpVersion = $options['auto_upgrade_http_version'];
            unset($options['auto_upgrade_http_version']);
        }
        $clone->options = $options;

        return $clone;
    }

    public function remember(object $response, object $request, object $promise): void
    {
        $this->promisePool?->attach($response, [$request, $promise]);
    }

    public function resolve(object $response): string
    {
        if (!$this->promisePool?->contains($response)) {
            return 'missing';
        }

        [$request, $promise] = $this->promisePool[$response];

        return $request->name . ':' . $promise->name;
    }

    public function describe(): string
    {
        return ($this->autoUpgradeHttpVersion ? 'auto' : 'manual') . ':' . implode(',', array_keys($this->options));
    }
}

function main(): void
{
    $client = new SymfonyHttpClientPoolCase();
    $response = (object) ['name' => 'response'];
    $request = (object) ['name' => 'request'];
    $promise = (object) ['name' => 'promise'];

    $client->remember($response, $request, $promise);
    var_dump($client->resolve($response));
    var_dump($client->resolve((object) ['name' => 'other']));

    $clone = $client->withOptions(['auto_upgrade_http_version' => false, 'timeout' => 3]);
    var_dump($client->describe());
    var_dump($clone->describe());
}
?>
--EXPECT--
string(15) "request:promise"
string(7) "missing"
string(5) "auto:"
string(14) "manual:timeout"
