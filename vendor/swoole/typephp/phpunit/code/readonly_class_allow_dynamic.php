<?php
#[AllowDynamicProperties]
readonly class Cfg { public int $port; public function __construct() { $this->port = 80; } }

function main() {}
