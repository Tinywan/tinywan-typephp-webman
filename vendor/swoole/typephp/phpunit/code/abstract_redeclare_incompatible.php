<?php
abstract class A { abstract public function f(): int; }
abstract class B extends A { abstract public function f(): string; }

function main() {}
