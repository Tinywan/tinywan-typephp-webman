--TEST--
Bug #27295 (memory leak inside sscanf())
--SKIPIF--
<?php
echo 'skip AOT limitation';
?>
--FILE--
<?php
$strings = array("foo = bar", "bar = foo");
foreach( $strings as $string )
{
    sscanf( $string, "%s = %[^[]]", $var, $val );
    echo "$var = $val\n";
}
?>
--EXPECT--
foo = bar
bar = foo
