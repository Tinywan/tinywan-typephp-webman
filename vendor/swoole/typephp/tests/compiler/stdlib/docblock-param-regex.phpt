--TEST--
docblock param regex with recursive named groups
--FILE--
<?php

function parse_doc_param_name(string $value): array
{
    $matches = [];
    preg_match('/^\s*(?<type>[\w\|\\\\]+(?<parens>\((?<inparens>(?:(?&parens)|[^(){}[\]<>]*+))++\)|\{(?&inparens)\}|\[(?&inparens)\]|<(?&inparens)>)*+(?::(?&type))?)\s*(\.\.\.)?\$(?<name>\w+).*$/', $value, $matches);

    return [
        $matches['type'] ?? '',
        $matches['name'] ?? '',
    ];
}

function main(): void
{
    var_dump(parse_doc_param_name('callable(array<string, int>):mixed $handler callback'));
    var_dump(parse_doc_param_name('int ...$items'));
    var_dump(parse_doc_param_name('$missingType'));
}
?>
--EXPECT--
array(2) {
  [0]=>
  string(34) "callable(array<string, int>):mixed"
  [1]=>
  string(7) "handler"
}
array(2) {
  [0]=>
  string(3) "int"
  [1]=>
  string(5) "items"
}
array(2) {
  [0]=>
  string(0) ""
  [1]=>
  string(0) ""
}
