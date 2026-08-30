--TEST--
Nullsafe operator - method and property access
--FILE--
<?php
class User {
    public function getName(): string {
        return 'Alice';
    }
}

function main() {
    $user1 = null;
    var_dump($user1?->getName());
    $user2 = new User();
    var_dump($user2?->getName());
}
?>
--EXPECT--
NULL
string(5) "Alice"

