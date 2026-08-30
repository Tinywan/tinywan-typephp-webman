<?php
function main() {


var_dump(
    (new class {
        function test(){
            return (static function(){ return compact('this'); })();
        }
    })->test()
);

var_dump(compact('this'));

var_dump((function(){ return compact('this'); })());


}
