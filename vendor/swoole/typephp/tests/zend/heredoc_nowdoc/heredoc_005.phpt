--TEST--
unbraced complex variable replacement test (heredoc)
--FILE--
<?php
function main() {
    require_once __DIR__ . '/nowdoc.inc';

    $a = 1;
    $b = 2;
    $c = array( 'c' => 3, );
    $d = new d;

    print <<<ENDOFHEREDOC
    This is heredoc test #s $a, $b, {$c['c']}, and $d->d.

    ENDOFHEREDOC;

    $x = <<<ENDOFHEREDOC
    This is heredoc test #s $a, $b, {$c['c']}, and $d->d.

    ENDOFHEREDOC;

    print "{$x}";
}
?>
--EXPECTF--
This is heredoc test #s 1, 2, 3, and 4.
This is heredoc test #s 1, 2, 3, and 4.
