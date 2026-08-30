--TEST--
Symfony pattern: dynamic registry property with array coalesce assignment
--FILE--
<?php

final class RegistryContainer
{
    public array $services = [];
    public array $privates = [];
    public array $factories = [];

    public function getService(string|false $registry, string $id, ?string $method, string|bool $load): mixed
    {
        if ('service_container' === $id) {
            return $this;
        }
        if (is_string($load)) {
            throw new RuntimeException($load);
        }
        if (null === $method) {
            return false !== $registry ? $this->{$registry}[$id] ?? null : null;
        }
        if (false !== $registry) {
            return $this->{$registry}[$id] ??= $load ? $this->load($method) : $this->{$method}($this);
        }
        if (!$load) {
            return $this->{$method}($this);
        }

        return ($factory = $this->factories[$id] ?? $this->factories['service_container'][$id] ?? null) ? $factory($this) : $this->load($method);
    }

    private function load(string $method): object
    {
        return $this->{$method}($this);
    }

    private function createLogger(self $container): object
    {
        return (object) ['id' => 'logger', 'count' => count($container->services) + count($container->privates)];
    }
}

function main(): void
{
    $container = new RegistryContainer();
    $container->factories['service_container']['mailer'] = static fn (RegistryContainer $container): object => (object) ['id' => 'mailer'];

    $logger = $container->getService('services', 'logger', 'createLogger', false);
    $sameLogger = $container->getService('services', 'logger', 'createLogger', false);
    $privateLogger = $container->getService('privates', 'logger', 'createLogger', true);
    $mailer = $container->getService(false, 'mailer', 'createLogger', true);

    var_dump($logger === $sameLogger);
    var_dump($logger->id, $logger->count);
    var_dump($privateLogger->id, $privateLogger->count);
    var_dump($mailer->id);
}
?>
--EXPECT--
bool(true)
string(6) "logger"
int(0)
string(6) "logger"
int(1)
string(6) "mailer"
