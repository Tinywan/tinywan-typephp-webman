--TEST--
default array property
--FILE--
<?php
function main() {
    $o = new stdClass();
    $year = 1995;
    $first = 'php';
    $last = '.net';
    $o->{0} = $year;
    $o->{1} = $first;
    $o->{2} = $last;
    var_dump($o);
}
?>
--EXPECTF--
object(stdClass)#%d (3) {
  ["0"]=>
  int(1995)
  ["1"]=>
  string(3) "php"
  ["2"]=>
  string(4) ".net"
}