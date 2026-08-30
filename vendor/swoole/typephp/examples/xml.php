<?php
$xml = simplexml_load_string('<root></root>');
var_dump($xml);
var_dump((bool)$xml); 

$obj = new stdClass();
var_dump((bool) $obj);

class UserClass {} 

$user = new UserClass();
var_dump((bool) $user);
