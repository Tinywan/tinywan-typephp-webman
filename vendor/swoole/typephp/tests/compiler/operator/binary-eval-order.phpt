--TEST--
Binary operator operands are evaluated left-to-right
--FILE--
<?php
function main()
{
    var_dump((print "sub-left\n") - (print "sub-right\n"));
    var_dump((print "nested-left\n") * ((print "nested-right-left\n") + (print "nested-right-right\n")));
    var_dump((print "eq-left\n") == (print "eq-right\n"));
    var_dump((print "same-left\n") === (print "same-right\n"));
    var_dump((print "spaceship-left\n") <=> (print "spaceship-right\n"));
    var_dump((print "pow-left\n") ** (print "pow-right\n"));
    var_dump(((print "logic-left\n") && false && (print "logic-right\n")) + (print "logic-after\n"));
}
?>
--EXPECT--
sub-left
sub-right
int(0)
nested-left
nested-right-left
nested-right-right
int(2)
eq-left
eq-right
bool(true)
same-left
same-right
bool(true)
spaceship-left
spaceship-right
int(0)
pow-left
pow-right
int(1)
logic-left
logic-after
int(1)
