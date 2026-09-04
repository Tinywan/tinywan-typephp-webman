<?php
class A { public function f(): int { return 1; } }
abstract class B extends A { abstract public function f(): int; }

function main() {}
