--TEST--
Constructor Property Promotion - PHP 8+ concise class syntax
--FILE--
<?php
// Test basic constructor promotion
class Point {
    public function __construct(
        public float $x = 0.0,
        public float $y = 0.0
    ) {}
    
    public function distance(): float {
        return sqrt($this->x * $this->x + $this->y * $this->y);
    }
}

// Test with visibility modifiers
class User {
    public function __construct(
        public string $name,
        private string $email,
        protected int $age = 18
    ) {}
    
    public function getEmail(): string {
        return $this->email;
    }
    
    public function getAge(): int {
        return $this->age;
    }
}

// Test with nullable types
class Product {
    public function __construct(
        public string $name,
        public float $price,
        public ?string $description = null,
        public int $quantity = 0
    ) {}
    
    public function getDescription(): string {
        return $this->description ?? 'No description';
    }
}

// Test mixed traditional and promoted
class Book {
    private static int $count = 0;
    
    public function __construct(
        public string $title,
        public string $author,
        private float $price
    ) {
        self::$count++;
    }
    
    public function getPriceWithTax(float $taxRate): float {
        return $this->price * (1 + $taxRate);
    }
    
    public static function getCount(): int {
        return self::$count;
    }
}

// Test readonly properties (PHP 8.1+)
class Coordinate {
    public function __construct(
        public readonly float $latitude,
        public readonly float $longitude
    ) {}
}

function main() {
    // Test basic promotion
    $point = new Point(3.0, 4.0);
    var_dump($point->x);
    var_dump($point->y);
    var_dump($point->distance());
    
    $origin = new Point();
    var_dump($origin->x);
    var_dump($origin->y);
    
    // Test with visibility
    $user = new User('Alice', 'alice@example.com', 25);
    var_dump($user->name);
    var_dump($user->getEmail());
    var_dump($user->getAge());
    
    // Test nullable
    $product1 = new Product('Laptop', 999.99, 'High-performance laptop', 10);
    var_dump($product1->name);
    var_dump($product1->price);
    var_dump($product1->getDescription());
    var_dump($product1->quantity);
    
    $product2 = new Product('Mouse', 29.99);
    var_dump($product2->name);
    var_dump($product2->getDescription());
    
    // Test mixed
    $book1 = new Book('PHP Guide', 'John Doe', 49.99);
    var_dump($book1->title);
    var_dump($book1->author);
    var_dump($book1->getPriceWithTax(0.1));
    
    $book2 = new Book('Advanced PHP', 'Jane Smith', 59.99);
    var_dump(Book::getCount());
    
    // Test readonly
    $coord = new Coordinate(40.7128, -74.0060);
    var_dump($coord->latitude);
    var_dump($coord->longitude);
}
?>
--EXPECT--
float(3)
float(4)
float(5)
float(0)
float(0)
string(5) "Alice"
string(17) "alice@example.com"
int(25)
string(6) "Laptop"
float(999.99)
string(23) "High-performance laptop"
int(10)
string(5) "Mouse"
string(14) "No description"
string(9) "PHP Guide"
string(8) "John Doe"
float(54.989000000000004)
int(2)
float(40.7128)
float(-74.006)
