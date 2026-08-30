--TEST--
Symfony pattern: get_object_vars parameters and array_key_last tail output
--FILE--
<?php

final class RouteParam
{
    public function __construct(
        public string $slug,
        public int $page = 1,
        private string $internal = 'hidden',
    ) {
    }
}

function mergeRouteParams(array $defaults, array $parameters): array
{
    foreach ($parameters as $key => $value) {
        if (is_object($value) && $vars = get_object_vars($value)) {
            unset($parameters[$key]);
            $parameters += $vars;
        }
    }

    return $parameters + $defaults;
}

function streamJsonParts(array $jsonParts): void
{
    echo $jsonParts[array_key_last($jsonParts)];
}

function main(): void
{
    var_dump(mergeRouteParams(['_route' => 'blog_show', 'page' => 1], [
        'post' => new RouteParam('symfony-aot', 3),
        'format' => 'json',
    ]));

    streamJsonParts(['{"items":', '[1,2,3]', '}']);
}
?>
--EXPECT--
array(4) {
  ["format"]=>
  string(4) "json"
  ["slug"]=>
  string(11) "symfony-aot"
  ["page"]=>
  int(3)
  ["_route"]=>
  string(9) "blog_show"
}
}
