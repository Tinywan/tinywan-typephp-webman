--TEST--
Binary, explicit octal and separated numeric literals preserve their values
--FILE--
<?php

function main(): void
{
    var_dump(0b1010);
    var_dump(0B1111);
    var_dump(0o17);
    var_dump(0O20);

    var_dump(1_234_567);
    var_dump(0b1010_0101);
    var_dump(0o7_5_5);
    var_dump(0xCA_FE);
    var_dump(1_2.5_0e1);
}
?>
--EXPECT--
int(10)
int(15)
int(15)
int(16)
int(1234567)
int(165)
int(493)
int(51966)
float(125)
