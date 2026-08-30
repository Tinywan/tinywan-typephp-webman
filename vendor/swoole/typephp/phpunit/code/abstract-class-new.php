<?php

abstract class AbstractBase {
    abstract function doSomething();
}

class User {
    function run() {
        $obj = new AbstractBase();
        $obj->doSomething();
    }
}

function main() {
    $u = new User();
    $u->run();
}
