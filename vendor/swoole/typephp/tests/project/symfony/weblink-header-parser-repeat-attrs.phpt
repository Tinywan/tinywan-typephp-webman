--TEST--
Symfony WebLink style header parser with match(true), rel ??= and repeated attributes
--FILE--
<?php
function symfony_parse_link_attributes(string $attributesString): array
{
    $attributes = [];
    $rels = null;

    if (preg_match_all('/;\s*([a-zA-Z0-9_-]+)(?:=(?:"((?:\\\"|[^"])*)"|([^;,\s]+)))?/', $attributesString, $attributeMatches, PREG_SET_ORDER)) {
        foreach ($attributeMatches as $pm) {
            $key = $pm[1];
            $value = match (true) {
                ($pm[2] ?? '') !== '' => stripcslashes($pm[2]),
                ($pm[3] ?? '') !== '' => $pm[3],
                default => true,
            };

            if ('rel' === $key) {
                $rels ??= true === $value ? [] : preg_split('/\s+/', $value, 0, PREG_SPLIT_NO_EMPTY);
            } elseif (is_array($attributes[$key] ?? null)) {
                $attributes[$key][] = $value;
            } elseif (isset($attributes[$key])) {
                $attributes[$key] = [$attributes[$key], $value];
            } else {
                $attributes[$key] = $value;
            }
        }
    }

    return [$rels, $attributes];
}

function main(): void
{
    [$rels, $attributes] = symfony_parse_link_attributes('; rel="preload module"; rel=ignored; as=script; title="a \"quoted\" title"; hreflang=en; hreflang=fr; disabled');

    var_dump($rels);
    var_dump($attributes);
}
?>
--EXPECT--
array(2) {
  [0]=>
  string(7) "preload"
  [1]=>
  string(6) "module"
}
array(4) {
  ["as"]=>
  string(6) "script"
  ["title"]=>
  string(16) "a "quoted" title"
  ["hreflang"]=>
  array(2) {
    [0]=>
    string(2) "en"
    [1]=>
    string(2) "fr"
  }
  ["disabled"]=>
  bool(true)
}
