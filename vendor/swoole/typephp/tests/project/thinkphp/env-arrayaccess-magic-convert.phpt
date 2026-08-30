--TEST--
ThinkPHP Env pattern: ArrayAccess, magic accessors and nested env flattening
--FILE--
<?php

class ThinkEnvLike implements ArrayAccess
{
    private array $data = [];
    private array $convert = [
        'true' => true,
        'false' => false,
        'off' => false,
        'on' => true,
    ];

    public function get(?string $name = null, mixed $default = null): mixed
    {
        if ($name === null) {
            return $this->data;
        }

        $name = strtoupper(str_replace('.', '_', $name));
        if (isset($this->data[$name])) {
            $result = $this->data[$name];
            if (is_string($result) && isset($this->convert[$result])) {
                return $this->convert[$result];
            }
            return $result;
        }

        return $default;
    }

    public function set(mixed $env, mixed $value = null): void
    {
        if (is_array($env)) {
            $env = array_change_key_case($env, CASE_UPPER);
            foreach ($env as $key => $val) {
                if (is_array($val)) {
                    foreach ($val as $k => $v) {
                        if (is_string($k)) {
                            $this->data[$key . '_' . strtoupper($k)] = $v;
                        } else {
                            $this->data[$key][$k] = $v;
                        }
                    }
                } else {
                    $this->data[$key] = $val;
                }
            }
        } else {
            $name = strtoupper(str_replace('.', '_', $env));
            $this->data[$name] = $value;
        }
    }

    public function has(string $name): bool
    {
        return !is_null($this->get($name));
    }

    public function __set(string $name, mixed $value): void
    {
        $this->set($name, $value);
    }

    public function __get(string $name): mixed
    {
        return $this->get($name);
    }

    public function __isset(string $name): bool
    {
        return $this->has($name);
    }

    public function offsetSet(mixed $name, mixed $value): void
    {
        $this->set($name, $value);
    }

    public function offsetExists(mixed $name): bool
    {
        return $this->__isset($name);
    }

    public function offsetUnset(mixed $name): void
    {
        throw new Exception('not support: unset');
    }

    public function offsetGet(mixed $name): mixed
    {
        return $this->get($name);
    }
}

function main(): void
{
    $env = new ThinkEnvLike();
    $env->set([
        'app' => ['debug' => 'true', 'hosts' => ['a', 'b']],
        'feature' => 'off',
    ]);
    $env['database.host'] = 'localhost';
    $env->cache_enabled = 'on';

    var_dump($env->get('app.debug'));
    var_dump($env['feature']);
    var_dump($env->database_host);
    var_dump(isset($env->cache_enabled));
    var_dump($env->get());
}
?>
--EXPECT--
bool(true)
bool(false)
string(9) "localhost"
bool(true)
array(5) {
  ["APP_DEBUG"]=>
  string(4) "true"
  ["APP_HOSTS"]=>
  array(2) {
    [0]=>
    string(1) "a"
    [1]=>
    string(1) "b"
  }
  ["FEATURE"]=>
  string(3) "off"
  ["DATABASE_HOST"]=>
  string(9) "localhost"
  ["CACHE_ENABLED"]=>
  string(2) "on"
}
