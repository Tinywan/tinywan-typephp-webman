--TEST--
Named Arguments - PHP 8+ function call syntax
--FILE--
<?php
// Test named arguments with required params
function multiply($a, $b, $scale = 1) {
    return ($a * $b) * $scale;
}

// Test all optional parameters
function greet($greeting = 'Hello', $name = 'World', $punctuation = '!') {
    return $greeting . ', ' . $name . $punctuation;
}

// Test mixed positional and named
function format_string($prefix, $content, $suffix = '') {
    return $prefix . $content . $suffix;
}

// Test named arguments order independence
function build_query($table, $fields = ['*'], $where = '', $limit = 100) {
    return [
        'table' => $table,
        'fields' => $fields,
        'where' => $where,
        'limit' => $limit,
    ];
}

function main() {
    // Test named arguments with math
    var_dump(multiply(a: 5, b: 10));
    var_dump(multiply(a: 5, b: 10, scale: 2));
    var_dump(multiply(scale: 3, a: 4, b: 6));
    
    // Test all optional with named
    var_dump(greet());
    var_dump(greet(greeting: 'Hi'));
    var_dump(greet(name: 'Alice'));
    var_dump(greet(punctuation: '?', greeting: 'How are you', name: ''));
    
    // Test mixed positional and named (positional must come first)
    var_dump(format_string('[', 'content', ']'));
    var_dump(format_string('[', 'content', suffix: ')'));

    // Test build query with various combinations
    $query1 = build_query(table: 'users');
    var_dump($query1);
    
    $query2 = build_query(
        table: 'products',
        fields: ['id', 'name', 'price'],
        where: 'active = 1'
    );
    var_dump($query2);
    
    $query3 = build_query(
        limit: 10,
        table: 'orders',
        where: 'status = "pending"'
    );
    var_dump($query3);
}
?>
--EXPECT--
int(50)
int(100)
int(72)
string(13) "Hello, World!"
string(10) "Hi, World!"
string(13) "Hello, Alice!"
string(14) "How are you, ?"
string(9) "[content]"
string(9) "[content)"
array(4) {
  ["table"]=>
  string(5) "users"
  ["fields"]=>
  array(1) {
    [0]=>
    string(1) "*"
  }
  ["where"]=>
  string(0) ""
  ["limit"]=>
  int(100)
}
array(4) {
  ["table"]=>
  string(8) "products"
  ["fields"]=>
  array(3) {
    [0]=>
    string(2) "id"
    [1]=>
    string(4) "name"
    [2]=>
    string(5) "price"
  }
  ["where"]=>
  string(10) "active = 1"
  ["limit"]=>
  int(100)
}
array(4) {
  ["table"]=>
  string(6) "orders"
  ["fields"]=>
  array(1) {
    [0]=>
    string(1) "*"
  }
  ["where"]=>
  string(18) "status = "pending""
  ["limit"]=>
  int(10)
}