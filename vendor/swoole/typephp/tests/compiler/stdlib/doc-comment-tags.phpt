--TEST--
doc comment tag extraction with preg_match_all set order
--FILE--
<?php

function parse_doc_tags(array $comments): array
{
    $tags = [];

    foreach ($comments as $comment) {
        if (preg_match_all('/@(\w+)\s+(.*)$/m', $comment, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $tagName = $match[1];
                $tagValue = trim($match[2]);

                if (!isset($tags[$tagName])) {
                    $tags[$tagName] = [];
                }

                $tags[$tagName][] = $tagValue;
            }
        }
    }

    return $tags;
}

function main(): void
{
    $comment = "summary\n@param int \$id\n@param string \$name user name\n@return bool";
    var_dump(parse_doc_tags([$comment]));
}
?>
--EXPECT--
array(2) {
  ["param"]=>
  array(2) {
    [0]=>
    string(7) "int $id"
    [1]=>
    string(22) "string $name user name"
  }
  ["return"]=>
  array(1) {
    [0]=>
    string(4) "bool"
  }
}
