--TEST--
Magic Methods - __get, __set
--SKIPIF--
--FILE--
<?php
class MagicClass {
    public array $data = [];
    public string $propStr = 'default value';
    
    // __get and __set
    public function __get(string $name): mixed {
        return $this->data[$name] ?? null;
    }
    
    public function __set(string $name, mixed $value): void {
        $this->data[$name] = $value;
    }
    
    // __isset and __unset
    public function __isset(string $name): bool {
        return isset($this->data[$name]);
    }
    
    public function __unset(string $name): void {
        unset($this->data[$name]);
    }
}

function main() {
    $magic = new MagicClass();
    
    // Test __set and __get
    $magic->property = 'test value';
    var_dump($magic->property);

    $magic->propStr = 'new value';
    var_dump($magic->propStr);
    
    $magic->number = 42;
    var_dump($magic->number);

    // Test __isset and __unset
    var_dump(isset($magic->property));
    var_dump(isset($magic->nonexistent));

    unset($magic->property);
    var_dump(isset($magic->property));
}
?>
--EXPECT--
string(10) "test value"
string(9) "new value"
int(42)
bool(true)
bool(false)
bool(false)

