<?php

trait TestTraitProp {
    private $prop = 999;
}

class Base {
    use TestTraitProp;
}
class User extends Base {
    public $prop;
}

function main() {
    $u = new User();
    $u->prop = 12;
    var_dump($u);
}
