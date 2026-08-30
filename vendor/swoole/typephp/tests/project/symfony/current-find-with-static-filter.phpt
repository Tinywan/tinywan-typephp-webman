--TEST--
Symfony pattern: current() over filtered find result with static arrow callback
--FILE--
<?php

class SymfonyLikeProfiler
{
    public function __construct(private array $profiles)
    {
    }

    public function find(?string $ip, ?string $url, int $limit, ?string $method, ?string $start, ?string $end, ?int $statusCode, callable $filter): array
    {
        return array_slice(array_values(array_filter($this->profiles, $filter)), 0, $limit);
    }
}

class SymfonyLikeProfilerController
{
    public function __construct(private SymfonyLikeProfiler $profiler)
    {
    }

    public function latest(string $profileType): ?array
    {
        if ($latest = current($this->profiler->find(null, null, 1, null, null, null, null, static fn ($profile) => $profileType === $profile['virtual_type']))) {
            return $latest;
        }

        return null;
    }
}

function main(): void
{
    $controller = new SymfonyLikeProfilerController(new SymfonyLikeProfiler([
        ['token' => 'a', 'virtual_type' => 'command'],
        ['token' => 'b', 'virtual_type' => 'request'],
    ]));

    var_dump($controller->latest('request'));
}
?>
--EXPECT--
array(2) {
  ["token"]=>
  string(1) "b"
  ["virtual_type"]=>
  string(7) "request"
}
