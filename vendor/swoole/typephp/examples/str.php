<?php
$s = '123';
$key = new stdClass();
var_dump(isset($s[true]));
var_dump($s[$key]);
