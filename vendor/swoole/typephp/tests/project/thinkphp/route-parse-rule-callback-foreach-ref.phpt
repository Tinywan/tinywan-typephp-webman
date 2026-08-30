--TEST--
ThinkPHP Route pattern: preg_replace_callback use ref and foreach ref match cast
--FILE--
<?php

class ThinkRouteRuleLike
{
    private array $pattern = [
        'id' => 'int',
        'price' => 'float',
    ];

    public array $vars = [];

    public function parseRule(string $rule, string $route, string $url, array $matches = []): string
    {
        $extraParams = true;
        $search = $replace = [];
        $depr = '/';

        foreach ($matches as $key => $value) {
            $search[] = '<' . $key . '>';
            $replace[] = $value;
            $search[] = '{' . $key . '}';
            $replace[] = $value;
            $search[] = ':' . $key;
            $replace[] = $value;

            if (str_contains($value, $depr)) {
                $extraParams = false;
            }
        }

        $route = str_replace($search, $replace, $route);

        if ($extraParams) {
            $count = substr_count($rule, '/');
            $extra = array_slice(explode('|', $url), $count + 1);
            $this->parseUrlParams(implode('/', $extra), $matches);
        }

        foreach ($matches as $key => &$val) {
            if (isset($this->pattern[$key]) && in_array($this->pattern[$key], ['\d+', 'int', 'float'], true)) {
                $val = match ($this->pattern[$key]) {
                    'int', '\d+' => (int) $val,
                    'float' => (float) $val,
                    default => $val,
                };
            } elseif (in_array($key, ['__module__', '__controller__', '__action__'], true)) {
                unset($matches[$key]);
            }
        }
        unset($val);

        $this->vars = $matches;
        return $route;
    }

    private function parseUrlParams(string $url, array &$var = []): void
    {
        if ($url) {
            preg_replace_callback('/(\w+)\/([^\/]+)/', function ($match) use (&$var) {
                $var[$match[1]] = strip_tags($match[2]);
            }, $url);
        }
    }
}

function main(): void
{
    $rule = new ThinkRouteRuleLike();
    var_dump($rule->parseRule('shop/<id>', 'product/:id', 'shop|42|price/12.5/__action__/show', ['id' => '42']));
    var_dump($rule->vars);
}
?>
--EXPECT--
string(10) "product/42"
array(2) {
  ["id"]=>
  int(42)
  ["price"]=>
  float(12.5)
}
