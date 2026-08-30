--TEST--
Nullsafe operator - method and property access
--FILE--
<?php
class User1 {
    public function getName(): string {
        return 'Alice';
    }
}

class User2 {
    public ?User1 $user = null;
    public function getUser() {
        return $this->user;
    }
}

function main() {
    $user = new User2();
    var_dump($user?->getUser()?->getName());

    $user->user = new User1();
    var_dump($user?->getUser()?->getName());
}
?>
--EXPECT--
NULL
string(5) "Alice"
