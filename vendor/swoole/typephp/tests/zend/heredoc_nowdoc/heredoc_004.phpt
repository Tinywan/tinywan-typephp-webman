--TEST--
braces variable replacement test (heredoc)
--FILE--
<?php

$a = 1;
$b = 2;

print <<<ENDOFHEREDOC
This is heredoc test #{$a}.

ENDOFHEREDOC;

$x = <<<ENDOFHEREDOC
This is heredoc test #{$b}.

ENDOFHEREDOC;

print "{$x}";

?>
--EXPECT--
This is heredoc test #1.
This is heredoc test #2.
