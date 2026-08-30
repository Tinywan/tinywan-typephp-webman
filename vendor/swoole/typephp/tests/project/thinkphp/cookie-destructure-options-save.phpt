--TEST--
ThinkPHP Cookie pattern: option normalization, destructuring and trailing call args
--FILE--
<?php

class ThinkCookieRequestLike
{
    public array $cookie = [];

    public function setCookie(string $name, mixed $value): void
    {
        $this->cookie[$name] = $value;
    }
}

class ThinkCookieLike
{
    private array $config = [
        'expire' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => false,
        'samesite' => '',
    ];
    private array $cookie = [];
    public array $saved = [];

    public function __construct(private ThinkCookieRequestLike $request, array $config = [])
    {
        $this->config = array_merge($this->config, array_change_key_case($config));
    }

    public function set(string $name, string $value, mixed $option = null): void
    {
        if ($option !== null) {
            if (is_numeric($option) || $option instanceof DateTimeInterface) {
                $option = ['expire' => $option];
            }
            $config = array_merge($this->config, array_change_key_case($option));
        } else {
            $config = $this->config;
        }

        if ($config['expire'] instanceof DateTimeInterface) {
            $expire = $config['expire']->getTimestamp();
        } else {
            $expire = !empty($config['expire']) ? 1000 + intval($config['expire']) : 0;
        }

        $this->setCookie($name, $value, $expire, $config);
        $this->request->setCookie($name, $value);
    }

    public function forever(string $name, string $value = '', mixed $option = null): void
    {
        if (is_null($option) || is_numeric($option)) {
            $option = [];
        }

        $option['expire'] = 315360000;
        $this->set($name, $value, $option);
    }

    private function setCookie(string $name, string $value, int $expire, array $option = []): void
    {
        $this->cookie[$name] = [$value, $expire, $option];
    }

    public function save(): void
    {
        foreach ($this->cookie as $name => $val) {
            [$value, $expire, $option] = $val;
            $this->saveCookie(
                (string) $name,
                $value,
                $expire,
                $option['path'],
                $option['domain'],
                (bool) $option['secure'],
                (bool) $option['httponly'],
                $option['samesite'],
            );
        }
    }

    private function saveCookie(string $name, string $value, int $expire, string $path, string $domain, bool $secure, bool $httponly, string $samesite): void
    {
        $this->saved[$name] = compact('value', 'expire', 'path', 'domain', 'secure', 'httponly', 'samesite');
    }
}

function main(): void
{
    $request = new ThinkCookieRequestLike();
    $cookie = new ThinkCookieLike($request, ['SameSite' => 'lax', 'Secure' => true]);
    $cookie->set('token', 'abc', new DateTimeImmutable('@42'));
    $cookie->forever('remember', 'yes', ['HttpOnly' => true]);
    $cookie->save();

    var_dump($request->cookie);
    var_dump($cookie->saved);
}
?>
--EXPECT--
array(2) {
  ["token"]=>
  string(3) "abc"
  ["remember"]=>
  string(3) "yes"
}
array(2) {
  ["token"]=>
  array(7) {
    ["value"]=>
    string(3) "abc"
    ["expire"]=>
    int(42)
    ["path"]=>
    string(1) "/"
    ["domain"]=>
    string(0) ""
    ["secure"]=>
    bool(true)
    ["httponly"]=>
    bool(false)
    ["samesite"]=>
    string(3) "lax"
  }
  ["remember"]=>
  array(7) {
    ["value"]=>
    string(3) "yes"
    ["expire"]=>
    int(315361000)
    ["path"]=>
    string(1) "/"
    ["domain"]=>
    string(0) ""
    ["secure"]=>
    bool(true)
    ["httponly"]=>
    bool(true)
    ["samesite"]=>
    string(3) "lax"
  }
}
