--TEST--
Union Types and Intersection Types - PHP 8+ type system
--SKIPIF--
--FILE--
<?php
// Test union types
class Shape {
    public function getName(): string {
        return "Shape";
    }
}

class Circle extends Shape {
    private float $radius;
    
    public function __construct(float $radius) {
        $this->radius = $radius;
    }
    
    public function getArea(): float {
        return M_PI * $this->radius * $this->radius;
    }
}

class Square extends Shape {
    private float $side;
    
    public function __construct(float $side) {
        $this->side = $side;
    }
    
    public function getArea(): float {
        return $this->side * $this->side;
    }
}

function process_shape(Shape|float $shape): float|string {
    if ($shape instanceof Shape) {
        return $shape->getArea();
    } else {
        return "Invalid shape: " . $shape;
    }
}

// Test nullable union types
function format_value(int|string|null $value): string {
    if ($value === null) {
        return "No value";
    }
    return "Value: " . $value;
}

// Test mixed type (PHP 8.0+)
function handle_mixed(mixed $data): mixed {
    return $data;
}

// Test never type (PHP 8.0+)
function redirect(string $url): never {
    throw new \RuntimeException("Redirecting to: " . $url);
}

function main() {
    // Test union types with instanceof
    $circle = new Circle(5.0);
    var_dump(process_shape($circle));
    
    $square = new Square(4.0);
    var_dump(process_shape($square));
    
    var_dump(process_shape(100.5));
    
    // Test nullable union
    var_dump(format_value(42));
    var_dump(format_value("hello"));
    var_dump(format_value(null));
    
    // Test mixed type
    var_dump(handle_mixed(42));
    var_dump(handle_mixed("string"));
    var_dump(handle_mixed([1, 2, 3]));
    var_dump(handle_mixed(new Circle(1.0)));
    
    // Test never type (will throw exception)
    try {
        redirect("http://example.com");
    } catch (RuntimeException $e) {
        var_dump($e->getMessage());
    }
}
?>
--EXPECT--
float(78.53981633974483)
float(16)
string(20) "Invalid shape: 100.5"
string(9) "Value: 42"
string(12) "Value: hello"
string(8) "No value"
int(42)
string(6) "string"
array(3) {
  [0]=>
  int(1)
  [1]=>
  int(2)
  [2]=>
  int(3)
}
object(Circle)#3 (1) {
  ["radius":"Circle":private]=>
  float(1)
}
string(34) "Redirecting to: http://example.com"
