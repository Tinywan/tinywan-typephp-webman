--TEST--
ThinkPHP Request pattern: dynamic property cache and method fallback
--FILE--
<?php

class ThinkRequestLike
{
    public array $post = [];
    public array $get = [];
    private string $varMethod = '_method';
    private string $method = '';

    public function post(): array
    {
        return $this->post;
    }

    public function get(): array
    {
        return $this->get;
    }

    public function method(): string
    {
        if (!$this->method && isset($this->post[$this->varMethod])) {
            $method = strtolower($this->post[$this->varMethod]);
            if (in_array($method, ['get', 'post'], true)) {
                $this->method = strtoupper($method);
                $this->{$method} = $this->post;
            }
            unset($this->post[$this->varMethod]);
        }

        return $this->method ?: 'GET';
    }

    public function has(string $name, string $type = 'post'): bool
    {
        $param = empty($this->$type) ? $this->$type() : $this->$type;
        foreach (explode('.', $name) as $key) {
            if (!isset($param[$key])) {
                return false;
            }
            $param = $param[$key];
        }
        return true;
    }
}

function main(): void
{
    $request = new ThinkRequestLike();
    $request->post = [
        '_method' => 'get',
        'user' => ['name' => 'thinkphp'],
    ];

    var_dump($request->method());
    var_dump($request->has('user.name', 'get'));
    var_dump($request->post);
}
?>
--EXPECT--
string(3) "GET"
bool(true)
array(1) {
  ["user"]=>
  array(1) {
    ["name"]=>
    string(8) "thinkphp"
  }
}
