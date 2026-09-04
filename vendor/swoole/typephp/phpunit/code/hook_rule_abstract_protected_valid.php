<?php
abstract class Box { abstract protected int $x { get; } }
class Crate extends Box { protected int $x { get => 1; } }
trait Boxed { abstract protected int $y { get; } }

function main() {}
