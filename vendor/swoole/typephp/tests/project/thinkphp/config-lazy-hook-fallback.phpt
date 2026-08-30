--TEST--
ThinkPHP Config pattern: lazy hook fallback and dotted key lookup
--XFAIL--
Known AOT bug: nullable mixed default/value flow can be narrowed incorrectly after dotted array lookup.
--FILE--
<?php

class ThinkConfigLike
{
    private array $config = [];
    private array $hook = [];

    public function hook(Closure $callback, ?string $key = null): void
    {
        $this->hook[$key ?? 'global'] = $callback;
    }

    public function set(array $config, ?string $name = null): array
    {
        if (empty($name)) {
            $this->config = array_merge($this->config, array_change_key_case($config));
            return $this->config;
        }

        $result = isset($this->config[$name]) ? array_merge($this->config[$name], $config) : $config;
        $this->config[$name] = $result;
        return $result;
    }

    public function get(?string $name = null, mixed $default = null): mixed
    {
        if (empty($name)) {
            return $this->config;
        }

        if (!str_contains($name, '.')) {
            $name = strtolower($name);
            $result = $this->config[$name] ?? [];
            return $this->hook ? $this->lazy($name, $result, []) : $result;
        }

        $item = explode('.', $name);
        $item[0] = strtolower($item[0]);
        $config = $this->config;

        foreach ($item as $val) {
            if (isset($config[$val])) {
                $config = $config[$val];
            } else {
                return $this->hook ? $this->lazy($name, null, $default) : $default;
            }
        }

        return $this->hook ? $this->lazy($name, $config, $default) : $config;
    }

    private function lazy(string $name, mixed $value = null, mixed $default = null): mixed
    {
        $key = strpos($name, '.') ? strstr($name, '.', true) : $name;
        if (isset($this->hook[$key])) {
            $call = $this->hook[$key];
        } elseif (isset($this->hook['global'])) {
            $call = $this->hook['global'];
        }

        if (isset($call)) {
            $result = call_user_func_array($call, [$name, $value]);
            if (is_null($result)) {
                return $default;
            }
        }

        return $result ?? ($value ?: $default);
    }
}

function main(): void
{
    $config = new ThinkConfigLike();
    $config->set(['Debug' => true, 'cache' => ['ttl' => 60]]);
    $config->hook(fn ($name, $value) => $name === 'cache.missing' ? null : ['hooked', $name, $value]);

    var_dump($config->get('debug'));
    var_dump($config->get('cache.ttl'));
    var_dump($config->get('cache.missing', 'fallback'));
}
?>
--EXPECT--
array(3) {
  [0]=>
  string(6) "hooked"
  [1]=>
  string(5) "debug"
  [2]=>
  bool(true)
}
array(3) {
  [0]=>
  string(6) "hooked"
  [1]=>
  string(9) "cache.ttl"
  [2]=>
  int(60)
}
string(8) "fallback"
