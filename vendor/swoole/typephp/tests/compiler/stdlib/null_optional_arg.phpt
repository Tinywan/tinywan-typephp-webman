--TEST--
Nullable and non-nullable builtin parameters preserve strict null semantics
--FILE--
<?php
declare(strict_types=1);

function main()
{
    $s = 'hello world';
    var_dump(substr($s, 6, null));
    var_dump(substr($s, 6) === substr($s, 6, null));
    var_dump(substr($s, 0, null) === $s);

    try {
        substr($s, null);
        echo "substr-offset-null=missing TypeError\n";
    } catch (TypeError $error) {
        echo "substr-offset-null=TypeError\n";
    }

    try {
        strpos($s, 'o', null);
        echo "strpos-offset-null=missing TypeError\n";
    } catch (TypeError $error) {
        echo "strpos-offset-null=TypeError\n";
    }

    try {
        stripos($s, 'O', null);
        echo "stripos-offset-null=missing TypeError\n";
    } catch (TypeError $error) {
        echo "stripos-offset-null=TypeError\n";
    }

    try {
        strrpos('hello hello', 'o', null);
        echo "strrpos-offset-null=missing TypeError\n";
    } catch (TypeError $error) {
        echo "strrpos-offset-null=TypeError\n";
    }

    try {
        strstr($s, 'o', null);
        echo "strstr-before-needle-null=missing TypeError\n";
    } catch (TypeError $error) {
        echo "strstr-before-needle-null=TypeError\n";
    }

    var_dump(str_repeat('ab', 3));

    try {
        explode(' ', 'a b c d', null);
        echo "explode-limit-null=missing TypeError\n";
    } catch (TypeError $error) {
        echo "explode-limit-null=TypeError\n";
    }
}

?>
--EXPECT--
string(5) "world"
bool(true)
bool(true)
substr-offset-null=TypeError
strpos-offset-null=TypeError
stripos-offset-null=TypeError
strrpos-offset-null=TypeError
strstr-before-needle-null=TypeError
string(6) "ababab"
explode-limit-null=TypeError
