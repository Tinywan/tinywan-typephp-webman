<?php
// Redeclaring an inherited abstract contract compatibly is legal, a parent's
// private method is not inherited, and a trait's abstract requirement may be
// satisfied by an inherited concrete method.
abstract class A { abstract public function f(): int; }
abstract class B extends A { abstract public function f(): int; }

class C { private function g(): int { return 1; } }
abstract class D extends C { abstract public function g(): int; }

trait RequiresF { abstract public function h(): int; }
class E { public function h(): int { return 1; } }
class F extends E { use RequiresF; }

function main() {}
