<?php
interface I1 { public function f(): int; }
interface I2 { public function f(): string; }
interface J extends I1, I2 {}

function main() {}
