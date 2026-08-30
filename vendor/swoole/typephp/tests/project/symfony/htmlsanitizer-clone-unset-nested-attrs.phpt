--TEST--
Symfony HtmlSanitizer pattern: clone config, unset nested array state, then write attributes
--FILE--
<?php

final class SanitizerConfig
{
    public array $allowedElements = [];
    public array $blockedElements = [];
    public array $droppedElements = [];

    public function allowElement(string $element, array|string $attributes = []): static
    {
        $clone = clone $this;
        unset($clone->blockedElements[$element], $clone->droppedElements[$element]);

        $clone->allowedElements[$element] = [];
        foreach ((array) $attributes as $allowedAttr) {
            $clone->allowedElements[$element][$allowedAttr] = true;
        }

        return $clone;
    }
}

function main(): void
{
    $config = new SanitizerConfig();
    $config->blockedElements['a'] = true;

    $next = $config->allowElement('a', ['href', 'title']);
    var_dump($config->blockedElements);
    var_dump($next->blockedElements);
    var_dump($next->allowedElements);
}
?>
--EXPECT--
array(1) {
  ["a"]=>
  bool(true)
}
array(0) {
}
array(1) {
  ["a"]=>
  array(2) {
    ["href"]=>
    bool(true)
    ["title"]=>
    bool(true)
  }
}
