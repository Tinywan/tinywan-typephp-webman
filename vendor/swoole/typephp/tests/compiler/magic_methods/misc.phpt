--TEST--
Magic Methods - __get, __set, __call, __invoke etc.
--FILE--
<?php
class Point {
    public function __construct(
        private float $x = 0.0,
        private float $y = 0.0
    ) {}
    
    // __clone
    public function __clone() {
        $this->x *= 2;
        $this->y *= 2;
    }
    
    public function getX(): float {
        return $this->x;
    }
    
    public function getY(): float {
        return $this->y;
    }
}

class SerializableClass {
    public function __construct(
        public string $name,
        public int $value
    ) {}
    
    // __serialize and __unserialize (PHP 7.4+)
    public function __serialize(): array {
        return [
            'name' => strtoupper($this->name),
            'value' => $this->value * 2,
        ];
    }
    
    public function __unserialize(array $data): void {
        $this->name = strtolower($data['name']);
        $this->value = $data['value'] / 2;
    }
}

function main() {
    // Test __clone
    $point1 = new Point(5.0, 10.0);
    $point2 = clone $point1;
    var_dump($point1->getX());
    var_dump($point2->getX());
    var_dump($point1->getY());
    var_dump($point2->getY());
    
    // Test __serialize and __unserialize
    $obj = new SerializableClass('Test', 100);
    $serialized = serialize($obj);
    $unserialized = unserialize($serialized);
    var_dump($unserialized->name);
    var_dump($unserialized->value);
}
?>
--EXPECT--
float(5)
float(10)
float(10)
float(20)
string(4) "test"
int(100)