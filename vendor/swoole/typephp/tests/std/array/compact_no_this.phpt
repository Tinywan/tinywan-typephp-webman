--TEST--
compact() without object context
--SKIPIF--
<?php die("skip AOT compiler rejects compact('this') outside class method"); ?>
--FILE--
<?php

var_dump(
    (new class {
        function test(){
            return (static function(){ return compact('this'); })();
        }
    })->test()
);

var_dump(compact('this'));

var_dump((function(){ return compact('this'); })());

?>
--EXPECT--
array(0) {
}
array(0) {
}
array(0) {
}
