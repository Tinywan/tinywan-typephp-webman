--TEST--
C++ signature parameter splitter with nested templates and defaults
--FILE--
<?php

function split_cpp_parameters(string $paramsStr): array
{
    $params = [];
    $current = '';
    $depth = 0;
    $length = strlen($paramsStr);

    for ($i = 0; $i < $length; $i++) {
        $char = $paramsStr[$i];

        if ($char === '<' || $char === '(' || $char === '[') {
            $depth++;
            $current .= $char;
        } elseif ($char === '>' || $char === ')' || $char === ']') {
            $depth--;
            $current .= $char;
        } elseif ($char === ',' && $depth === 0) {
            $params[] = $current;
            $current = '';
        } else {
            $current .= $char;
        }
    }

    if (!empty($current)) {
        $params[] = $current;
    }

    return $params;
}

function parse_cpp_parameter(string $param): array
{
    $param = trim($param);
    $param = preg_replace('/\s*=\s*.*$/', '', $param);

    if (preg_match('/^(.+?)\s+(\w+)\s*$/', $param, $matches)) {
        return [
            'type' => trim($matches[1]),
            'name' => trim($matches[2]),
        ];
    }

    return [
        'type' => $param,
        'name' => '',
    ];
}

function parse_cpp_parameters(string $signature, string $funcName): array
{
    $pattern = '/' . preg_quote($funcName, '/') . '\s*\((.*?)\)/s';
    if (!preg_match($pattern, $signature, $matches)) {
        return [];
    }

    $paramsStr = trim($matches[1]);
    if (empty($paramsStr) || $paramsStr === 'void') {
        return [];
    }

    return array_map('parse_cpp_parameter', split_cpp_parameters($paramsStr));
}

function main(): void
{
    $signature = 'static std::vector<std::pair<int, std::string>> build(std::map<int, std::string> values, const char* name = "x", Callback<int,string> cb)';
    var_dump(parse_cpp_parameters($signature, 'build'));
    var_dump(parse_cpp_parameters('void reset(void)', 'reset'));
}
?>
--EXPECT--
array(3) {
  [0]=>
  array(2) {
    ["type"]=>
    string(26) "std::map<int, std::string>"
    ["name"]=>
    string(6) "values"
  }
  [1]=>
  array(2) {
    ["type"]=>
    string(11) "const char*"
    ["name"]=>
    string(4) "name"
  }
  [2]=>
  array(2) {
    ["type"]=>
    string(20) "Callback<int,string>"
    ["name"]=>
    string(2) "cb"
  }
}
array(0) {
}
