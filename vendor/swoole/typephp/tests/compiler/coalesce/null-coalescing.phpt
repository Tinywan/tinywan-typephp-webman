--TEST--
Null Coalescing Operators - ?? and ??= syntax
--FILE--
<?php
// Test basic null coalescing operator (??)
function test_basic_coalesce($value) {
    return $value ?? 'default';
}

// Test chained null coalescing
function test_chained_coalesce($a, $b, $c) {
    return $a ?? $b ?? $c ?? 'all null';
}

// Test null coalescing assignment (??=)
class Config {
    private array $settings = [];
    
    public function setDefault(string $key, mixed $default): void {
        $this->settings[$key] ??= $default;
    }
    
    public function get(string $key): mixed {
        return $this->settings[$key] ?? null;
    }
    
    public function getAll(): array {
        return $this->settings;
    }
}

// Test with array access
function test_array_coalesce(array $data, string $key) {
    return $data[$key] ?? 'not set';
}

// Test with nested arrays
function test_nested_coalesce(array $data) {
    return $data['user']['profile']['name'] ?? 'Anonymous';
}

// Test in expressions
function test_expression($value) {
    $result = ($value ?? 0) * 2;
    return $result;
}

function main() {
    // Test basic coalescing
    var_dump(test_basic_coalesce(null));
    var_dump(test_basic_coalesce('exists'));
    var_dump(test_basic_coalesce(0));
    var_dump(test_basic_coalesce(false));
    var_dump(test_basic_coalesce(''));
    
    // Test chained coalescing
    var_dump(test_chained_coalesce(null, null, 'third'));
    var_dump(test_chained_coalesce(null, 'second', 'third'));
    var_dump(test_chained_coalesce('first', 'second', 'third'));
    var_dump(test_chained_coalesce(null, null, null));
    
    // Test null coalescing assignment
    $config = new Config();
    $config->setDefault('debug', false);
    $config->setDefault('timeout', 30);
    $config->setDefault('debug', true); // Should not override
    var_dump($config->getAll());
    
    // Test array coalescing
    $arr1 = ['name' => 'John'];
    $arr2 = [];
    var_dump(test_array_coalesce($arr1, 'name'));
    var_dump(test_array_coalesce($arr1, 'age'));
    var_dump(test_array_coalesce($arr2, 'name'));
    
    // Test nested coalescing
    $data1 = ['user' => ['profile' => ['name' => 'Alice']]];
    $data2 = ['user' => []];
    $data3 = [];
    var_dump(test_nested_coalesce($data1));
    var_dump(test_nested_coalesce($data2));
    var_dump(test_nested_coalesce($data3));
    
    // Test in expressions
    var_dump(test_expression(null));
    var_dump(test_expression(5));
    var_dump(test_expression(0));
    
    // Test complex scenario
    $options = [
        'limit' => null,
        'offset' => 10,
    ];
    
    $limit = $options['limit'] ?? 100;
    $offset = $options['offset'] ?? 0;
    
    var_dump($limit);
    var_dump($offset);
}
?>
--EXPECT--
string(7) "default"
string(6) "exists"
int(0)
bool(false)
string(0) ""
string(5) "third"
string(6) "second"
string(5) "first"
string(8) "all null"
array(2) {
  ["debug"]=>
  bool(false)
  ["timeout"]=>
  int(30)
}
string(4) "John"
string(7) "not set"
string(7) "not set"
string(5) "Alice"
string(9) "Anonymous"
string(9) "Anonymous"
int(0)
int(10)
int(0)
int(100)
int(10)
