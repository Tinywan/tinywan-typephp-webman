<?php

class Base {
    private function doWork() {
        return 'base';
    }
}

class Child extends Base {
    private function doWork() {
        return 'child';
    }

    function run() {
        return $this->doWork();
    }
}

function main() {
    $c = new Child();
    echo $c->run();
}
