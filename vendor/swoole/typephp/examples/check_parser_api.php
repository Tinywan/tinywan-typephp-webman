<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PhpParser\ParserFactory;

// 检查正确的 API 用法
$parserFactory = new ParserFactory();
$parser = $parserFactory->create(ParserFactory::PREFER_PHP7);

var_dump($parser);
echo "Parser class: " . get_class($parser) . "\n";
echo "Has parse method: " . (method_exists($parser, 'parse') ? 'yes' : 'no') . "\n";

// 尝试解析一个简单的 PHP 代码
$code = '<?php echo "hello";';
try {
    $stmts = $parser->parse($code);
    echo "Parse successful\n";
    var_dump($stmts);
} catch (\PhpParser\Error $e) {
    echo "Parse error: " . $e->getMessage() . "\n";
}