--TEST--
Readonly Classes (PHP 8.2+)
--FILE--
<?php

// Test basic readonly class
#[\AllowDynamicProperties]
readonly class Point {
    public int $x;
    public int $y;
    
    public function __construct(int $x = 0, int $y = 0) {
        $this->x = $x;
        $this->y = $y;
    }
}

// Test readonly class with methods
readonly class Circle {
    public float $radius;
    
    public function __construct(float $radius) {
        $this->radius = $radius;
    }
    
    public function area(): float {
        return M_PI * $this->radius ** 2;
    }
    
    public function circumference(): float {
        return 2 * M_PI * $this->radius;
    }
}

// Test readonly class inheritance
readonly abstract class Shape {
    abstract public function area(): float;
}

readonly class Rectangle extends Shape {
    public function __construct(
        public float $width,
        public float $height
    ) {}
    
    public function area(): float {
        return $this->width * $this->height;
    }
}

function main() {
    // Test basic readonly class
    $point = new Point(10, 20);
    var_dump($point->x);
    var_dump($point->y);

    // Test readonly class with methods
    $circle = new Circle(5);
    var_dump($circle->area());
    var_dump($circle->circumference());

    // Test readonly class inheritance
    $rect = new Rectangle(10, 5);
    var_dump($rect->area());
}
?>
--EXPECT--
int(10)
int(20)
float(78.53981633974483)
float(31.41592653589793)
float(50)
