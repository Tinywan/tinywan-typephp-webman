<?php
$h = $i = $j = new stdClass();
$h->val = $i->val = $j->val = 999;

$h->val = 343;
var_dump($h);
var_dump($i);
var_dump($j);

