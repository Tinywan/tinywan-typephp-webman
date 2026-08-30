--TEST--
Constructor Property Promotion - type edge cases (array, nullable, object, union, untyped)
--FILE--
<?php

// Test 1: array type
class ArrayHolder {
    public function __construct(
        private array $items
    ) {}

    public function getItems(): array {
        return $this->items;
    }

    public function hasItem(string $key): bool {
        return isset($this->items[$key]);
    }
}

// Test 2: nullable array
class NullableArrayHolder {
    public function __construct(
        private ?array $data = null
    ) {}

    public function getData(): ?array {
        return $this->data;
    }
}

// Test 3: object type
class Dependency {}
class ServiceConsumer {
    public function __construct(
        private Dependency $dep
    ) {}

    public function hasDependency(): bool {
        return $this->dep !== null;
    }
}

// Test 4: union type (int|string) - should devolve to var/mixed
class UnionHolder {
    public function __construct(
        private int|string $value = 0
    ) {}

    public function getValue(): int|string {
        return $this->value;
    }
}

// Test 5: untyped (no type hint)
class UntypedHolder {
    public function __construct(
        private $anything = null
    ) {}

    public function getAnything() {
        return $this->anything;
    }
}

// Test 6: protected promoted
class ProtectedHolder {
    public function __construct(
        protected int $count = 0
    ) {}

    public function getCount(): int {
        return $this->count;
    }
}

// Test 7: array + nullable combination
class MixedArray {
    public function __construct(
        private array $required,
        private ?array $optional = null,
        private string $label = ''
    ) {}

    public function describe(): string {
        $reqCount = count($this->required);
        $optCount = $this->optional ? count($this->optional) : 0;
        return "{$this->label}: required={$reqCount}, optional={$optCount}";
    }
}

function main() {
    // Test 1: array
    $ah = new ArrayHolder(['a' => 1, 'b' => 2]);
    var_dump($ah->getItems());
    var_dump($ah->hasItem('a'));
    var_dump($ah->hasItem('x'));

    // Test 2: nullable array with default null
    $nah = new NullableArrayHolder();
    var_dump($nah->getData());

    $nah2 = new NullableArrayHolder(['x', 'y']);
    var_dump($nah2->getData());

    // Test 3: object type
    $dep = new Dependency();
    $sc = new ServiceConsumer($dep);
    var_dump($sc->hasDependency());

    // Test 4: union type
    $uh_int = new UnionHolder(42);
    var_dump($uh_int->getValue());

    $uh_str = new UnionHolder('hello');
    var_dump($uh_str->getValue());

    // Test 5: untyped
    $ut = new UntypedHolder([1, 2, 3]);
    var_dump($ut->getAnything());

    $ut2 = new UntypedHolder();
    var_dump($ut2->getAnything());

    // Test 6: protected promoted
    $ph = new ProtectedHolder(10);
    var_dump($ph->getCount());

    // Test 7: mixed
    $ma = new MixedArray(['x' => 10], ['y' => 20], 'test');
    echo $ma->describe() . "\n";
}

?>
--EXPECT--
array(2) {
  ["a"]=>
  int(1)
  ["b"]=>
  int(2)
}
bool(true)
bool(false)
NULL
array(2) {
  [0]=>
  string(1) "x"
  [1]=>
  string(1) "y"
}
bool(true)
int(42)
string(5) "hello"
array(3) {
  [0]=>
  int(1)
  [1]=>
  int(2)
  [2]=>
  int(3)
}
NULL
int(10)
test: required=1, optional=1
