<?php
$o = new StdClass();
$o->a = 1999;
parse_str("c=2000", $o->a);
$prop = $o->a;
$prop = 111;
var_dump($o->a);
