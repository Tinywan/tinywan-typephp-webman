<?php
class C extends B {
    public function __construct()
    {
        echo "C::__construct()\n";
        parent::__construct();
    }
}