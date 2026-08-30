<?php

abstract class AbsBase {
    abstract function show();
}

class Child extends AbsBase {
    function show() {
        echo "Call to function show()\n";
    }
    function error() {
        parent::show();
    }
}

function main() {
    $t = new Child();
    $t->show();
    $t->error();
}
