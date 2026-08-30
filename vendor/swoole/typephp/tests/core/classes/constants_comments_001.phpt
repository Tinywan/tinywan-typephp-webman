--TEST--
Class constants and doc comments
--INI--
opcache.save_comments=1
--FILE--
<?php
class X {
    /** comment X1 */
    const X1 = 1;
    const X2 = 2;
    /** comment X3 */
    const X3 = 3;
}

class Y extends X {
    /** comment Y1 */
    const Y1 = 1;
    const Y2 = 2;
    /** comment Y3 */
    const Y3 = 3;
}

function main() {
    $r = new ReflectionClass('Y');
    foreach ($r->getReflectionConstants() as $rc) {
        echo $rc->getName() . " : " . $rc->getValue() . "\n";
    }
}
?>
--EXPECT--
X1 : 1
X2 : 2
X3 : 3
Y1 : 1
Y2 : 2
Y3 : 3

