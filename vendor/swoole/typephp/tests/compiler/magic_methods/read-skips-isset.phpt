--TEST--
Magic Methods - a plain property read invokes __get, never __isset
--FILE--
<?php
declare(strict_types=1);

class MagicReadTest
{
    private array $data = ['name' => 'TypePHP'];

    public function __get(string $name)
    {
        echo "__get($name)\n";
        return $this->data[$name] ?? null;
    }

    public function __isset(string $name)
    {
        echo "__isset($name)\n";
        return isset($this->data[$name]);
    }
}

class MagicIssetTrueTest
{
    public function __isset(string $name): bool
    {
        echo "__isset($name)\n";
        return true;
    }

    public function __get(string $name)
    {
        echo "__get($name)\n";
        return null;
    }
}

function main(): void
{
    $obj = new MagicReadTest();

    echo "--- plain read ---\n";
    var_dump($obj->aaa);

    echo "--- isset ---\n";
    var_dump(isset($obj->aaa));

    echo "--- empty ---\n";
    var_dump(empty($obj->aaa));

    echo "--- isset true does not read ---\n";
    $issetTrue = new MagicIssetTrueTest();
    var_dump(isset($issetTrue->value));
}
?>
--EXPECTF--
--- plain read ---
__get(aaa)
NULL
--- isset ---
__isset(aaa)
bool(false)
--- empty ---
__isset(aaa)
bool(true)
--- isset true does not read ---
__isset(value)
bool(true)
