--TEST--
Error suppression operator @
--FILE--
<?php

function main() {
    $arr = ["x" => 1];
    $ret = @$arr["missing"];
    var_dump($ret);

    $result = @file_get_contents("/nonexistent/file/path");
    var_dump($result);

    echo "done\n";
}

?>
--EXPECT--
NULL
bool(false)
done
