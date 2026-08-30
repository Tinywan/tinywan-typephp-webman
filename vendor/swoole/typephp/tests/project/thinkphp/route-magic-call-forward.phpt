--TEST--
ThinkPHP Route pattern: __call forwarding with call_user_func_array
--FILE--
<?php

class ThinkRuleGroupLike
{
    private array $rules = [];

    public function option(string $name, mixed $value): static
    {
        $this->rules[$name] = $value;
        return $this;
    }

    public function getRules(): array
    {
        return $this->rules;
    }
}

class ThinkRouteLike
{
    public function __construct(private ThinkRuleGroupLike $group)
    {
    }

    public function __call(string $method, array $args): mixed
    {
        return call_user_func_array([$this->group, $method], $args);
    }
}

function main(): void
{
    $group = new ThinkRuleGroupLike();
    $route = new ThinkRouteLike($group);

    $result = $route->option('middleware', ['auth', 'log']);

    var_dump($result === $group);
    var_dump($group->getRules());
}
?>
--EXPECT--
bool(true)
array(1) {
  ["middleware"]=>
  array(2) {
    [0]=>
    string(4) "auth"
    [1]=>
    string(3) "log"
  }
}
