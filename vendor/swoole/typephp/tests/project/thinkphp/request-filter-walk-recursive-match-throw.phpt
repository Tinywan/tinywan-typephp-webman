--TEST--
ThinkPHP Request pattern: array_walk_recursive by-ref filter and match throw type cast
--XFAIL--
Known AOT bug: mixed parameter narrowed to array after array_walk_recursive path and reused for scalar input.
--FILE--
<?php

class ThinkRequestFilterLike
{
    private mixed $filter = null;

    public function filter(mixed $filter = null): mixed
    {
        if ($filter === null) {
            return $this->filter;
        }

        $this->filter = $filter;
        return $this;
    }

    public function input(array $data, string|bool $name = '', mixed $default = null, string|array|null $filter = ''): mixed
    {
        if (false === $name) {
            return $data;
        }

        $name = (string) $name;
        if ('' !== $name) {
            if (str_contains($name, '/')) {
                [$name, $type] = explode('/', $name);
            }

            $data = $this->getData($data, $name);
        }

        return $this->filterData($data, $filter, $name, $default, $type ?? '');
    }

    private function filterData(mixed $data, mixed $filter, string $name, mixed $default, string $type): mixed
    {
        if ($data === null) {
            return $default;
        }

        $filter = $this->getFilter($filter, $default);
        if (is_array($data)) {
            array_walk_recursive($data, [$this, 'filterValue'], $filter);
        } else {
            $this->filterValue($data, $name, $filter);
        }

        if ($type) {
            $this->typeCast($data, $type);
        }

        return $data;
    }

    private function getData(array $data, string $name, mixed $default = null): mixed
    {
        foreach (explode('.', $name) as $val) {
            if (isset($data[$val])) {
                $data = $data[$val];
            } else {
                return $default;
            }
        }

        return $data;
    }

    private function getFilter(mixed $filter, mixed $default): array
    {
        if ($filter === null) {
            $filter = [];
        } else {
            $filter = $filter ?: $this->filter;
            if (is_string($filter) && !str_contains($filter, '/')) {
                $filter = explode(',', $filter);
            } else {
                $filter = (array) $filter;
            }
        }

        $filter[] = $default;
        return $filter;
    }

    public function filterValue(mixed &$value, mixed $key, array $filters): void
    {
        $default = array_pop($filters);
        foreach ($filters as $filter) {
            if (is_callable($filter)) {
                if ($value === null) {
                    continue;
                }
                $value = call_user_func($filter, $value);
            } elseif (is_scalar($value) && is_string($filter) && str_contains($filter, '/')) {
                if (!preg_match($filter, (string) $value)) {
                    $value = $default;
                    break;
                }
            }
        }
    }

    private function typeCast(mixed &$data, string $type): void
    {
        $data = match (strtolower($type)) {
            'a' => (array) $data,
            'b' => (bool) $data,
            'd' => (int) $data,
            'f' => (float) $data,
            's' => is_scalar($data) ? (string) $data : throw new InvalidArgumentException('variable type error:' . gettype($data)),
            default => $data,
        };
    }
}

function main(): void
{
    $request = new ThinkRequestFilterLike();
    $request->filter(fn ($value) => is_string($value) ? trim($value) : $value);

    var_dump($request->input(['user' => ['name' => ' thinkphp ', 'role' => ' admin ']], 'user'));
    var_dump($request->input(['id' => '42'], 'id/d'));

    try {
        $request->input(['user' => ['name' => 'thinkphp']], 'user/s');
    } catch (InvalidArgumentException $e) {
        echo "type error\n";
    }
}
?>
--EXPECT--
array(2) {
  ["name"]=>
  string(8) "thinkphp"
  ["role"]=>
  string(5) "admin"
}
int(42)
type error
