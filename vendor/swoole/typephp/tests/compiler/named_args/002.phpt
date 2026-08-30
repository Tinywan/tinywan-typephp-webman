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
    // Test all defaults
    $user3 = create_user(
        name: 'Bob',
        email: 'bob@example.com',
        active: false
    );
    var_dump($user3);
}
?>
--EXPECT--
array(4) {
  ["name"]=>
  string(3) "Bob"
  ["email"]=>
  string(15) "bob@example.com"
  ["age"]=>
  int(18)
  ["active"]=>
  bool(false)
}
