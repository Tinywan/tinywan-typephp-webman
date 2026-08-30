<?php
class Base {
    private $prop = 999;
    public function dump()
    {
        var_dump($this->prop);
    }
}
class User extends Base {
    public $prop;
}

function main() {
    $u = new User();
    $u->prop = 12;
    var_dump($u);
    $u->dump();
}
