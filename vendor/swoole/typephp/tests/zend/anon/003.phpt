--TEST--
reusing anonymous classes
--FILE--
<?php
$i = 0;
while ($i++<10) {
    var_dump(new class($i) {
        public $i;
        public function __construct($i) {
            $this->i = $i;
        }
    });
}
?>
--EXPECTF--
object(%s)#1 (1) {
  ["i"]=>
  int(1)
}
object(%s)#1 (1) {
  ["i"]=>
  int(2)
}
object(%s)#1 (1) {
  ["i"]=>
  int(3)
}
object(%s)#1 (1) {
  ["i"]=>
  int(4)
}
object(%s)#1 (1) {
  ["i"]=>
  int(5)
}
object(%s)#1 (1) {
  ["i"]=>
  int(6)
}
object(%s)#1 (1) {
  ["i"]=>
  int(7)
}
object(%s)#1 (1) {
  ["i"]=>
  int(8)
}
object(%s)#1 (1) {
  ["i"]=>
  int(9)
}
object(%s)#1 (1) {
  ["i"]=>
  int(10)
}
