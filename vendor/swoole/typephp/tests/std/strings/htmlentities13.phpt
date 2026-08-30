--TEST--
htmlentities() test 13 (default_charset / EUC-JP)
--SKIPIF--
<?php
echo 'skip Test output differs under AOT compilation';
?>
--INI--
output_handler=
default_charset=EUC-JP
filter.default=unsafe_raw
--FILE--
<?php
print ini_get('default_charset')."\n";
var_dump(htmlentities("\xa1\xa2\xa1\xa3\xa1\xa4", ENT_QUOTES, ''));
?>
--EXPECTF--
EUC-JP

Notice: htmlentities(): Only basic entities substitution is supported for multi-byte encodings other than UTF-8; functionality is equivalent to htmlspecialchars in %s on line %d
string(6) "¡¢¡£¡¤"
