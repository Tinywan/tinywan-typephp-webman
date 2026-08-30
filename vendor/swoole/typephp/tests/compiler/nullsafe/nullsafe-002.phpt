--TEST--
Nullsafe operator - method and property access
--FILE--
<?php
class Customer {
    public function __construct(
        public ?Address $address = null,
        public string $name = "John"
    ) {}
    
    public function getCountry(): ?string {
        return $this->address?->country ?? 'Unknown';
    }
}

class Address {
    public function __construct(
        public string $street = "Main St",
        public string $city = "NYC",
        public string $country = "USA"
    ) {}
    
    public function getFullAddress(): string {
        return "{$this->street}, {$this->city}";
    }
}

// Test 6: Nullsafe with method returning object
class ChainTest {
    public ?Child $child = null;

    public function getChild(): ?Child {
        return $this->child;
    }
}

class Child {
    public string $name = "Alice";

    public function getName(): string {
        return $this->name;
    }
}

function main() {
    // Test 1: Nullsafe method call on non-null object
    $customer1 = new Customer(new Address("5th Avenue", "New York", "USA"));
    echo $customer1->address?->getFullAddress() . "\n";
    
    // Test 2: Nullsafe method call on null object
    $customer2 = new Customer(null);
    var_dump($customer2->address?->getFullAddress());
    
    // Test 3: Nullsafe property access on non-null object
    echo $customer1->address?->city . "\n";
    
    // Test 4: Nullsafe property access on null object
    var_dump($customer2->address?->city);
    
    // Test 5: Chained nullsafe calls
    echo strtoupper($customer1?->address?->getFullAddress());

    $test = new ChainTest();
    $test->child = new Child();
    
    // Test 7: Chained nullsafe method calls
    echo $test->getChild()?->getName() . "\n";

    // Test 8: Chained nullsafe with null in middle
    $test2 = new ChainTest();
    var_dump($test2->getChild()?->getName());
    
    // Test 9: Nullsafe property access in expression
    $value = $customer1->address?->street ?? 'No street';
    echo $value . "\n";
    
    // Test 10: Multiple nullsafe in same expression
    echo ($customer1->address?->city ?? 'Unknown') . ", " . ($customer1->address?->country ?? 'Unknown') . "\n";
}
?>
--EXPECT--
5th Avenue, New York
NULL
New York
NULL
5TH AVENUE, NEW YORKAlice
NULL
5th Avenue
New York, USA
