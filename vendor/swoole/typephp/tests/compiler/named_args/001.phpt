--TEST--
Named Arguments - PHP 8+ function call syntax
--FILE--
<?php
// Test basic named arguments
function create_user($name, $email, $age = 18, $active = true) {
    return [
        'name' => $name,
        'email' => $email,
        'age' => $age,
        'active' => $active,
    ];
}

function main() {
    // Test basic named arguments
    $user1 = create_user(
        name: 'John Doe',
        email: 'john@example.com',
        age: 25
    );
    var_dump($user1);
    
    // Test skipping optional parameters
    $user2 = create_user(
        name: 'Jane Smith',
        email: 'jane@example.com'
    );
    var_dump($user2);
}
?>
--EXPECT--
array(4) {
  ["name"]=>
  string(8) "John Doe"
  ["email"]=>
  string(16) "john@example.com"
  ["age"]=>
  int(25)
  ["active"]=>
  bool(true)
}
array(4) {
  ["name"]=>
  string(10) "Jane Smith"
  ["email"]=>
  string(16) "jane@example.com"
  ["age"]=>
  int(18)
  ["active"]=>
  bool(true)
}

