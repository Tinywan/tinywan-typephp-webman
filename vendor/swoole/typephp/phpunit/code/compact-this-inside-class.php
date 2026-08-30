<?php
function main() {
    var_dump(
        (new class {
            function test() {
                return compact('this');
            }
        })->test()
    );
}
