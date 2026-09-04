<?php
// Compatible multi-extends (covariant), diamond inheritance, and a class
// method that satisfies both incompatible declarations are all legal.
interface I1 { public function f(): int; }
interface I2 { public function f(): int|string; }
interface J extends I1, I2 {}

interface Base { public function g(): int; }
interface B1 extends Base {}
interface B2 extends Base {}
interface Diamond extends B1, B2 {}

interface S1 { public function h(): int; }
interface S2 { public function h(): string; }
class C implements S1, S2 { public function h(): never { throw new Exception('x'); } }

function main() {}
