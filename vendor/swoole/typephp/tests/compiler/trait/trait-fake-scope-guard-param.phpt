--TEST--
Trait wrapper scope guard does not collide with a PHP parameter
--FILE--
<?php
trait GuardParameterTrait {
    public function passThrough(string $fake_scope_guard): string {
        return $fake_scope_guard;
    }
}

class GuardParameterUser {
    use GuardParameterTrait;
}

function main() {
    var_dump((new GuardParameterUser())->passThrough('ok'));
}
?>
--EXPECT--
string(2) "ok"
