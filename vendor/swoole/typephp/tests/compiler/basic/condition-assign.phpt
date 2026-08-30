--TEST--
condition assign
--FILE--
<?php
function main() {
    if ($value = true) {
        echo "success";
    }
}
?>
--EXPECT--
success
