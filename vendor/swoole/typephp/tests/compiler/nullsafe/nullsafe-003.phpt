--TEST--
Nullsafe operator - method and property access
--FILE--
<?php
class User {
    public ?stdClass $prop = null;
}

function main() {
    $user = new User();
    var_dump($user?->prop?->data);
    $user->prop = new stdClass();
    $user->prop->data = 'hello';
    var_dump($user?->prop?->data);
}
?>
--EXPECT--
NULL
string(5) "hello"
