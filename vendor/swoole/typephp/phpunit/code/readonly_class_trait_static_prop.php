<?php
trait Settings { public static int $port = 80; }
readonly class Cfg { use Settings; }

function main() {}
