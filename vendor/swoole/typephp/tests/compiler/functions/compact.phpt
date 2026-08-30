--TEST--
compact
--FILE--
<?php
function main()
{
    $city  = "San Francisco";
    $state = "CA";
    $event = "SIGGRAPH";
    $result = compact("event", "city", "state");
    var_dump($result);
}
?>
--EXPECT--
array(3) {
  ["event"]=>
  string(8) "SIGGRAPH"
  ["city"]=>
  string(13) "San Francisco"
  ["state"]=>
  string(2) "CA"
}