--TEST--
fn call 001
--FILE--
<?php
function main()
{
    if (\class_exists('\\\\Event', false)) {
        $className = '\\\\Event';
    } else {
        $className = '\Event';
    }
    var_dump($className);
}
?>
--EXPECT--
string(6) "\Event"
