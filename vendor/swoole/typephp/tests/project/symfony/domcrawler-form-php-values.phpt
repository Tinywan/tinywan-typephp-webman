--TEST--
Symfony DomCrawler pattern: parse_str values and recursive replace with unpack
--FILE--
<?php

function expand_form_values(array $fields): array
{
    $values = [];

    foreach ($fields as $name => $value) {
        $qs = http_build_query([$name => $value], '', '&');
        if ($qs) {
            parse_str($qs, $expandedValue);
            $varName = substr($name, 0, strlen(key($expandedValue)));
            $values[] = [$varName => current($expandedValue)];
        }
    }

    return array_replace_recursive([], ...$values);
}

function main(): void
{
    var_dump(expand_form_values([
        'user[name]' => 'Ada',
        'user[roles][0]' => 'admin',
        'user[roles][1]' => 'editor',
        'token' => 'abc',
    ]));
}
?>
--EXPECT--
array(2) {
  ["user"]=>
  array(2) {
    ["name"]=>
    string(3) "Ada"
    ["roles"]=>
    array(2) {
      [0]=>
      string(5) "admin"
      [1]=>
      string(6) "editor"
    }
  }
  ["token"]=>
  string(3) "abc"
}
