--TEST--
Heredoc and nowdoc string syntax
--FILE--
<?php

function main() {
    $name = "World";

    $heredoc1 = <<<EOT
Hello, $name!
This is a heredoc string.
EOT;

    $nowdoc1 = <<<'NOW'
Hello, $name!
This does NOT interpolate.
NOW;

    var_dump($heredoc1);
    var_dump($nowdoc1);

    echo "done\n";
}

?>
--EXPECT--
string(39) "Hello, World!
This is a heredoc string."
string(40) "Hello, $name!
This does NOT interpolate."
done
