<?php
trait Settings { public readonly int $port; }
readonly class Cfg { use Settings; public function __construct() { $this->port = 80; } }

function main() {}
