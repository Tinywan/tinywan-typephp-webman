--TEST--
dirname / basename edge cases
--FILE--
<?php
function main() {
    // dirname
    var_dump(dirname("/usr/local/bin"));
    var_dump(dirname("/usr/local/bin", 1));
    var_dump(dirname("/usr/local/bin", 2));
    var_dump(dirname("/usr/local/bin", 3));
    var_dump(dirname("/usr/"));
    var_dump(dirname("usr"));
    var_dump(dirname(""));

    // basename
    var_dump(basename("/usr/local/bin"));
    var_dump(basename("/usr/local/bin", "bin"));
    var_dump(basename("/"));
    var_dump(basename("usr"));
    var_dump(basename(""));
}
?>
--EXPECTREGEX--
string\(10\) "\/usr\/local"
string\(10\) "\/usr\/local"
string\(4\) "\/usr"
string\(1\) "[\\\/]"
string\(1\) "[\\\/]"
string\(1\) "\."
string\(0\) ""
string\(3\) "bin"
string\(3\) "bin"
string\(0\) ""
string\(3\) "usr"
string\(0\) ""
