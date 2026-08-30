<?php

namespace TypePhpTest\PythonTools;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use TypePhp\PythonTools\Converter\PythonToTypePhpConverter;

/**
 * py2php（tpc --convert-python-to-php）转换器的语法覆盖测试。
 *
 * 覆盖矩阵与 docs/PY2PHP.md 保持同步：每个支持的语法断言生成片段，
 * 每个不支持的语法断言 `{file}:{line}: unsupported Python syntax ...` 错误。
 *
 * 转换依赖真实 python3（AST 由 PythonAstLoader 通过 `python3` 子进程解析），
 * 环境缺少 python3 时整体跳过。
 */
final class PythonToTypePhpConverterTest extends TestCase
{
    private static ?bool $pythonAvailable = null;

    protected function setUp(): void
    {
        self::$pythonAvailable ??= $this->detectPython();
        if (!self::$pythonAvailable) {
            self::markTestSkipped('python3 is required to parse Python sources');
        }
    }

    private function detectPython(): bool
    {
        $process = @proc_open(['python3', '--version'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            return false;
        }
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }
        return proc_close($process) === 0;
    }

    private function convert(string $source, string $filename = 'case.py'): string
    {
        return (new PythonToTypePhpConverter())->convertSource($source, $filename);
    }

    // ---------------------------------------------------------------
    // 既有行为测试
    // ---------------------------------------------------------------

    public function testConvertsImportsFunctionsAndTopLevelCode(): void
    {
        $source = <<<'PYTHON'
import math
import os.path as path
from json import dumps as encode

def hypotenuse(x, y=4):
    return math.sqrt(x * x + y * y)

value = hypotenuse(3)
print(encode({"value": value}))
PYTHON;

        $php = $this->convert($source, 'example.py');

        self::assertStringContainsString('use python\\math;', $php);
        self::assertStringContainsString('use python\\os\\path;', $php);
        self::assertStringContainsString('function hypotenuse($x, $y = 4)', $php);
        self::assertStringContainsString('return math\\sqrt($x * $x + $y * $y);', $php);
        self::assertStringContainsString('function main(): void', $php);
        self::assertStringContainsString('python\\json\\dumps(python\\dict([\'value\' => $value]))', $php);
        self::assertStringContainsString('python\\print(', $php);
    }

    public function testPassDoesNotBecomeReturnAndPythonComparisonsStayExplicit(): void
    {
        $source = <<<'PYTHON'
def inspect_value(value, values):
    if value is None:
        pass
    return value in values
PYTHON;

        $php = $this->convert($source, 'comparison.py');

        self::assertStringContainsString('if ($value === null)', $php);
        self::assertStringContainsString('// pass', $php);
        self::assertStringContainsString('python\\operator\\contains($values, $value)', $php);
        self::assertStringNotContainsString('if ($value === null) {' . "\n        return;", $php);
    }

    public function testUnsupportedSyntaxReportsSourceLocation(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('sample.py:1');
        $this->expectExceptionMessage('ClassDef');

        $this->convert("class Demo:\n    pass\n", 'sample.py');
    }

    public function testModuleVariablesRemainVisibleInsideFunctions(): void
    {
        $php = $this->convert(<<<'PYTHON'
factor = 4

def scale(value):
    return value * factor

print(scale(3))
PYTHON, 'globals.py');

        self::assertStringContainsString('function scale($value)' . "\n{\n" . '    global $factor;', $php);
        self::assertStringContainsString("function main(): void\n{\n" . '    global $factor;', $php);
    }

    public function testOnlyTheFirstAttributeAfterAModuleAliasIsAModuleMember(): void
    {
        $php = $this->convert(<<<'PYTHON'
import sys

print(sys.version_info.major)
sys.stdout.write("hello")
print(f"{sys.version_info.minor}")
PYTHON, 'module-attribute.py');

        self::assertStringContainsString("echo sys\\version_info->major, \"\\n\";", $php);
        self::assertStringContainsString("sys\\stdout->write('hello');", $php);
        self::assertStringContainsString('echo sys\\version_info->minor->toString(), "\\n";', $php);
        self::assertStringNotContainsString('sys\\version_info\\major', $php);
        self::assertStringNotContainsString('sys\\stdout\\write', $php);
    }

    public function testLowersPrintAndSysExitOnlyWhenPhpHasTheSameBehavior(): void
    {
        $php = $this->convert(<<<'PYTHON'
import sys

print()
print("hello")
print(f"version: {sys.version_info.major}")
print(True)
print("same line", end="")
sys.exit()
sys.exit(2)
sys.exit("failure")
PYTHON, 'native-statements.py');

        self::assertStringContainsString('echo "\\n";', $php);
        self::assertStringContainsString('echo \'hello\', "\\n";', $php);
        self::assertStringContainsString('echo \'version: \' . sys\\version_info->major->toString(), "\\n";', $php);
        self::assertStringContainsString('python\\print(true);', $php);
        self::assertStringContainsString("python\\print('same line', end: '');", $php);
        self::assertStringContainsString("exit;\n    exit(2);", $php);
        self::assertStringContainsString("sys\\exit('failure');", $php);
    }

    // ---------------------------------------------------------------
    // 支持的语句
    // ---------------------------------------------------------------

    /** @dataProvider supportedStatementProvider */
    public function testSupportedStatements(string $python, array $contains, array $notContains = []): void
    {
        $php = $this->convert($python);
        foreach ($contains as $fragment) {
            self::assertStringContainsString($fragment, $php);
        }
        foreach ($notContains as $fragment) {
            self::assertStringNotContainsString($fragment, $php);
        }
    }

    public static function supportedStatementProvider(): array
    {
        return [
            '赋值语句' => [
                "x = 1\n",
                ['global $x;', '$x = 1;'],
            ],
            '增强赋值' => [
                "x = 1\nx += 2\nx -= 3\nx *= 4\nx %= 5\nx **= 2\n",
                ['$x += 2;', '$x -= 3;', '$x *= 4;', '$x %= 5;', '$x **= 2;'],
            ],
            '带注解的赋值' => [
                "x: int = 5\n",
                ['$x = 5;'],
            ],
            'return 无值与有值' => [
                "def f():\n    return\n\ndef g():\n    return 1\n",
                ["function f()\n{\n    return;", 'return 1;'],
            ],
            'if/elif/else 链' => [
                "x = 1\nif x == 1:\n    pass\nelif x == 2:\n    pass\nelse:\n    pass\n",
                ["if (\$x == 1)\n    {", "elseif (\$x == 2)\n    {", "else\n    {"],
            ],
            'while 循环' => [
                "while True:\n    continue\n",
                ["while (true)\n    {", 'continue;'],
            ],
            'for 循环转 foreach' => [
                "for i in range(10):\n    break\n",
                ['foreach (python\\range(10) as $i)', 'break;'],
            ],
            'pass 占位' => [
                "def f():\n    pass\n",
                ['// pass'],
            ],
            'del 名称' => [
                "x = 1\ndel x\n",
                ['unset($x);'],
            ],
            'del 属性' => [
                "o.name = 1\ndel o.name\n",
                ['$o->name = 1;', 'unset($o->name);'],
            ],
            'del 下标' => [
                "d = {}\nd['k'] = 1\ndel d['k']\n",
                ["\$d['k'] = 1;", "unset(\$d['k']);"],
            ],
            '函数内 global 声明' => [
                "g = 1\ndef f():\n    global g\n    g = 2\n",
                ['$g = 2;'],
            ],
            '模块字符串表达式转为注释' => [
                "\"\"\"module doc\"\"\"\nx = 1\n",
                ['/** module doc */'],
            ],
            'import 无别名取首段' => [
                "import os.path\n",
                ['use python\\os;'],
                ['use python\\os\\path'],
            ],
            'import 别名等于末段时省略 as' => [
                "import os.path as path\n",
                ['use python\\os\\path;'],
                [' as '],
            ],
            'import 别名与末段不同保留 as' => [
                "import os.path as ospath\n",
                ['use python\\os\\path as ospath;'],
            ],
            '链式赋值' => [
                "x = y = 1\n",
                ['global $x, $y;', '$x = $y = 1;'],
            ],
            '解构赋值转 toArray' => [
                "a, b = x\n",
                ['global $a, $b;', '[$a, $b] = $x->toArray();'],
            ],
            '解构元组字面量' => [
                "a, b = (1, 2)\n",
                ['[$a, $b] = python\\tuple([1, 2])->toArray();'],
            ],
            '解构到属性与下标' => [
                "o.a, d['k'] = pair\n",
                ["[\$o->a, \$d['k']] = \$pair->toArray();"],
            ],
            '整除增强赋值展开为函数调用' => [
                "x = 7\nx //= 2\n",
                ['$x = python\\operator\\floordiv($x, 2);'],
            ],
            '矩阵乘增强赋值展开为函数调用' => [
                "x = a\nx @= b\n",
                ['$x = python\\operator\\matmul($x, $b);'],
            ],
            'del 元组目标逐项展开' => [
                "x = 1\ny = 2\ndel (x, y)\n",
                ['unset($x);', 'unset($y);'],
            ],
            '纯注解声明转为注释且不登记全局' => [
                "x: int\ny = 1\n",
                ['// annotation-only declaration: x', 'global $y;'],
                ['global $x'],
            ],
            'main 函数重命名为 main_' => [
                "def main():\n    return 1\n\nprint(main())\n",
                ['function main_()', 'python\\print(main_());'],
            ],
        ];
    }

    /** 函数内 global 语句与自动注入的 global 会同时出现（冗余但合法，属已知行为）。 */
    public function testGlobalStatementDuplicatesAutoInjectedGlobal(): void
    {
        $php = $this->convert("g = 1\ndef f():\n    global g\n    g = 2\n");
        $functionBody = substr($php, strpos($php, 'function f()'), strpos($php, 'function main') - strpos($php, 'function f()'));
        self::assertSame(2, substr_count($functionBody, 'global $g;'));
    }

    // ---------------------------------------------------------------
    // 函数签名
    // ---------------------------------------------------------------

    /** @dataProvider functionSignatureProvider */
    public function testFunctionSignatures(string $python, string $signature): void
    {
        self::assertStringContainsString($signature, $this->convert($python));
    }

    public static function functionSignatureProvider(): array
    {
        return [
            '位置参数与默认值' => ["def f(x, y=4):\n    pass\n", 'function f($x, $y = 4)'],
            '仅关键字参数无默认值为 null' => ["def f(a, *, b):\n    pass\n", 'function f($a, $b = null)'],
            '仅关键字参数带默认值' => ["def f(a, *, b, c=3):\n    pass\n", 'function f($a, $b = null, $c = 3)'],
            '变长参数' => ["def f(*args):\n    pass\n", 'function f(...$args)'],
            '关键字变长参数' => ["def f(**kw):\n    pass\n", 'function f(...$kw)'],
            'lambda 参数与默认值' => ["f = lambda a, b=2: a + b\n", 'fn ($a, $b = 2) => $a + $b'],
        ];
    }

    // ---------------------------------------------------------------
    // 支持的表达式
    // ---------------------------------------------------------------

    /** @dataProvider supportedExpressionProvider */
    public function testSupportedExpressions(string $python, array $contains): void
    {
        $php = $this->convert($python);
        foreach ($contains as $fragment) {
            self::assertStringContainsString($fragment, $php);
        }
    }

    public static function supportedExpressionProvider(): array
    {
        return [
            '整数字面量' => ["x = 42\n", ['$x = 42;']],
            '负整数字面量' => ["x = -1\n", ['$x = -1;']],
            '浮点字面量' => ["x = 1.5\n", ['$x = 1.5;']],
            '字符串转义' => ["x = 'it\\'s'\n", ['$x = \'it\\\'s\';']],
            'None 转 null' => ["x = None\n", ['$x = null;']],
            '布尔字面量' => ["a = True\nb = False\n", ['$a = true;', '$b = false;']],
            'list 字面量' => ["x = [1, 2]\n", ['$x = python\\list([1, 2]);']],
            'tuple 字面量' => ["x = (1, 2)\n", ['$x = python\\tuple([1, 2]);']],
            'set 字面量' => ["x = {1, 2}\n", ['$x = python\\set([1, 2]);']],
            'dict 字面量' => ["x = {'k': 1}\n", ['$x = python\\dict([\'k\' => 1]);']],
            'dict 解包' => ["d = {**e, 'a': 1}\n", ["python\\dict([...\$e, 'a' => 1])"]],
            'list 解包' => ["x = [*a, 1]\n", ['python\\list([...$a, 1]);']],
            '嵌套容器' => [
                "x = [[1], (2,), {3}, {'k': 4}]\n",
                ['python\\list([python\\list([1]), python\\tuple([2]), python\\set([3]), python\\dict([\'k\' => 4])])'],
            ],
            '条件表达式' => ["x = 1 if True else 2\n", ['$x = (true ? 1 : 2);']],
            '下标访问' => ["x = [1]\ny = x[0]\n", ['$y = $x[0];']],
            '切片' => ["x = [1,2,3]\ny = x[1:]\n", ['$y = $x[python\\slice(1, null, null)];']],
            '完整切片' => ["x = [1,2,3]\ny = x[0:3:2]\n", ['python\\slice(0, 3, 2)']],
            '属性链' => ["x = obj.field.sub\n", ['$x = $obj->field->sub;']],
            '变量调用' => ["foo(1, 2)\n", ['$foo(1, 2);']],
            '关键字参数调用' => ["foo(x=1)\n", ['$foo(x: 1);']],
            '解包调用' => ["foo(*args)\n", ['$foo(...$args);']],
            '关键字解包调用' => ["foo(**kw)\n", ['$foo(...$kw);']],
            '内置函数映射命名空间' => ["n = len([1])\nm = max(1, 2)\n", ['$n = python\\len(', '$m = python\\max(']],
            '名字 this 转义' => ["this = 1\nprint(this)\n", ['$this_ = 1;', 'python\\print($this_);']],
            'f-string 名称插值' => ['x = 1' . "\n" . 'print(f"{x}")' . "\n", ['echo $x->toString(), "\\n";']],
            'f-string 文本与插值拼接' => ['x = 1' . "\n" . 'print(f"v={x}")' . "\n", ["echo 'v=' . \$x->toString(), \"\\n\";"]],
            'f-string 运算符整体加括号' => ['x = 1' . "\n" . 'print(f"{x + 1}")' . "\n", ['echo ($x + 1)->toString(), "\\n";']],
            '海象运算符' => ["if (n := 10):\n    print(n)\n", ['if (($n = 10))', 'python\\print($n);']],
        ];
    }

    // ---------------------------------------------------------------
    // 函数装饰器
    // ---------------------------------------------------------------

    public function testSimpleDecoratorRebindsModuleVariable(): void
    {
        $php = $this->convert(<<<'PYTHON'
def dec(f):
    return f

@dec
def greet():
    return 1

greet()
PYTHON);

        self::assertStringContainsString('$greet = dec(\'greet\');', $php);
        // 调用点经变量间接调用装饰结果，而不是直连原函数
        self::assertStringContainsString('$greet();', $php);
        self::assertStringNotContainsString('    greet();', $php);
    }

    public function testDecoratorFactoryEvaluatesBeforeRebinding(): void
    {
        $php = $this->convert(<<<'PYTHON'
def dec(prefix):
    return lambda f: f

@dec('x')
def greet():
    return 1
PYTHON);

        self::assertStringContainsString('$greet = dec(\'x\')(\'greet\');', $php);
    }

    public function testStackedDecoratorsApplyBottomUp(): void
    {
        $php = $this->convert(<<<'PYTHON'
def a(f):
    return f

def b(f):
    return f

@a
@b
def greet():
    return 1
PYTHON);

        self::assertStringContainsString('$greet = b(\'greet\');', $php);
        self::assertStringContainsString('$greet = a(\'greet\');', $php);
        self::assertLessThan(
            strpos($php, '$greet = a(\'greet\');'),
            strpos($php, '$greet = b(\'greet\');'),
        );
    }

    public function testImportedSymbolDecorator(): void
    {
        $php = $this->convert(<<<'PYTHON'
from functools import cache

@cache
def compute():
    return 1
PYTHON);

        self::assertStringContainsString('$compute = python\\functools\\cache(\'compute\');', $php);
    }

    /** 被装饰函数进入模块全局，函数内调用点经 global + 变量间接调用。 */
    public function testDecoratedFunctionCallInsideAnotherFunction(): void
    {
        $php = $this->convert(<<<'PYTHON'
def dec(f):
    return f

@dec
def greet():
    return 1

def run():
    return greet()
PYTHON);

        self::assertStringContainsString("function run()\n{\n    global \$greet;\n    return \$greet();", $php);
    }

    // ---------------------------------------------------------------
    // 运算符映射
    // ---------------------------------------------------------------

    /** @dataProvider binaryOperatorProvider */
    public function testBinaryOperators(string $operator, string $expected): void
    {
        self::assertStringContainsString($expected, $this->convert("x = a {$operator} b\n"));
    }

    public static function binaryOperatorProvider(): array
    {
        return [
            '加' => ['+', '$a + $b'],
            '减' => ['-', '$a - $b'],
            '乘' => ['*', '$a * $b'],
            '除' => ['/', '$a / $b'],
            '取模' => ['%', '$a % $b'],
            '幂' => ['**', '$a ** $b'],
            '左移' => ['<<', '$a << $b'],
            '右移' => ['>>', '$a >> $b'],
            '按位或' => ['|', '$a | $b'],
            '按位异或' => ['^', '$a ^ $b'],
            '按位与' => ['&', '$a & $b'],
            '整除转函数' => ['//', 'python\\operator\\floordiv($a, $b)'],
        ];
    }

    public function testMatmulOperator(): void
    {
        self::assertStringContainsString(
            'python\\operator\\matmul($a, $b)',
            $this->convert("x = a @ b\n"),
        );
    }

    /** @dataProvider unaryOperatorProvider */
    public function testUnaryOperators(string $python, string $expected): void
    {
        self::assertStringContainsString($expected, $this->convert($python));
    }

    public static function unaryOperatorProvider(): array
    {
        return [
            '取负' => ["x = -a\n", '$x = -$a;'],
            '取正' => ["x = +a\n", '$x = +$a;'],
            '逻辑非' => ["x = not a\n", '$x = !$a;'],
            '按位取反' => ["x = ~a\n", '$x = ~$a;'],
        ];
    }

    /** @dataProvider comparisonOperatorProvider */
    public function testComparisonOperators(string $operator, string $expected): void
    {
        self::assertStringContainsString($expected, $this->convert("x = a {$operator} b\n"));
    }

    public static function comparisonOperatorProvider(): array
    {
        return [
            '相等' => ['==', '$a == $b'],
            '不等' => ['!=', '$a != $b'],
            'is 转全等' => ['is', '$a === $b'],
            'is not 转不全等' => ['is not', '$a !== $b'],
            '小于' => ['<', '$a < $b'],
            '小于等于' => ['<=', '$a <= $b'],
            '大于' => ['>', '$a > $b'],
            '大于等于' => ['>=', '$a >= $b'],
            'in 转 contains 且参数交换' => ['in', 'python\\operator\\contains($b, $a)'],
            'not in 转 contains 取反' => ['not in', '!python\\operator\\contains($b, $a)'],
        ];
    }

    // ---------------------------------------------------------------
    // print / sys.exit 降级与遮蔽
    // ---------------------------------------------------------------

    /** @dataProvider printShadowingProvider */
    public function testPrintShadowingDisablesEchoLowering(string $python, string $expected): void
    {
        self::assertStringContainsString($expected, $this->convert($python));
    }

    public static function printShadowingProvider(): array
    {
        return [
            '用户定义 print 函数' => ["def print(x):\n    return x\nprint(1)\n", "function print(\$x)"],
            '用户函数遮蔽后直连调用' => ["def print(x):\n    return x\nprint(1)\n", "\n    print(1);\n"],
            'from import 遮蔽 print' => ["from logging import print\nprint(1)\n", 'python\\logging\\print(1);'],
            '模块全局变量遮蔽 print' => ["print = str\nprint(1)\n", 'python\\print(1);'],
            '浮点常量不兼容 echo' => ["print(1.5)\n", ['python\\print(1.5);'][0]],
            '关键字参数不降级' => ["print('a', sep='-')\n", "python\\print('a', sep: '-');"],
            '多参数空格连接' => ["print('a', 'b')\n", "echo 'a', ' ', 'b', \"\\n\";"],
        ];
    }

    /** @dataProvider sysExitProvider */
    public function testSysExitLowering(string $python, string $expected): void
    {
        self::assertStringContainsString($expected, $this->convert($python));
    }

    public static function sysExitProvider(): array
    {
        return [
            '无参转 exit' => ["import sys\nsys.exit()\n", "exit;\n"],
            '整数码转 exit(n)' => ["import sys\nsys.exit(2)\n", 'exit(2);'],
            '负整数码' => ["import sys\nsys.exit(-1)\n", 'exit(-1);'],
            '字符串参数不降级' => ["import sys\nsys.exit('fail')\n", "sys\\exit('fail');"],
            'from import 形式同样识别' => ["from sys import exit\nexit()\n", "exit;\n"],
        ];
    }

    // ---------------------------------------------------------------
    // 不支持的语法：统一断言 {file}:{line} 错误格式
    // ---------------------------------------------------------------

    /** @dataProvider unsupportedSyntaxProvider */
    public function testUnsupportedSyntax(string $python, string $message, string $filename = 'case.py'): void
    {
        try {
            $this->convert($python, $filename);
            self::fail('Expected RuntimeException: ' . $message);
        } catch (RuntimeException $exception) {
            self::assertStringContainsString($message, $exception->getMessage());
        }
    }

    public static function unsupportedSyntaxProvider(): array
    {
        return [
            '类定义' => ["class A:\n    pass\n", 'case.py:1: unsupported Python syntax ClassDef'],
            'with 语句' => ["with open('f') as fp:\n    pass\n", 'case.py:1: unsupported Python syntax With'],
            'raise 语句' => ["raise ValueError('x')\n", 'case.py:1: unsupported Python syntax Raise'],
            'try/except' => ["try:\n    pass\nexcept Exception:\n    pass\n", 'case.py:1: unsupported Python syntax Try'],
            'assert 语句' => ["assert True\n", 'case.py:1: unsupported Python syntax Assert'],
            'async def' => ["async def f():\n    pass\n", 'case.py:1: unsupported Python syntax AsyncFunctionDef'],
            'match 语句' => ["match x:\n    case 1:\n        pass\n", 'case.py:1: unsupported Python syntax Match'],
            'yield' => ["def f():\n    yield 1\n", 'case.py:2: unsupported Python syntax Yield'],
            'nonlocal' => ["def f():\n    x = 1\n    nonlocal x\n", 'case.py:3: unsupported Python syntax Nonlocal'],
            'and/or 布尔运算' => ["x = a and b\n", 'case.py:1: unsupported Python syntax BoolOp'],
            '列表推导式' => ["x = [i for i in range(3)]\n", 'case.py:1: unsupported Python syntax ListComp'],
            '字典推导式' => ["x = {k: v for k, v in d}\n", 'case.py:1: unsupported Python syntax DictComp'],
            '生成器表达式' => ["x = sum(i for i in range(3))\n", 'case.py:1: unsupported Python syntax GeneratorExp'],
            '非名称目标的链式赋值' => ["a.b = c = 1\n", 'case.py:1: unsupported Python syntax Assign: chained assignments are only supported for plain name targets'],
            '嵌套解构' => ["a, (b, c) = x\n", 'case.py:1: unsupported Python syntax Assign: nested destructuring'],
            '星号解构' => ["a, *b = x\n", 'case.py:1: unsupported Python syntax Assign: starred destructuring'],
            '链式解构' => ["a, b = c = x\n", 'case.py:1: unsupported Python syntax Assign: destructuring with chained targets'],
            'while/else' => ["while True:\n    pass\nelse:\n    pass\n", 'case.py:1: unsupported Python syntax While: while/else'],
            'for/else' => ["for i in x:\n    pass\nelse:\n    pass\n", 'case.py:1: unsupported Python syntax For: for/else'],
            'for 元组目标' => ["for a, b in x:\n    pass\n", 'case.py:1: unsupported Python syntax For: only a simple for-loop target'],
            '模块属性赋值' => ["import sys\nsys.stdout = None\n", 'case.py:2: unsupported Python syntax Attribute: Python module attributes cannot be assigned'],
            '相对导入' => ["from . import mod\n", 'case.py:1: unsupported Python syntax ImportFrom: relative imports'],
            '星号导入' => ["from os import *\n", 'case.py:1: unsupported Python syntax ImportFrom: star imports'],
            '嵌套函数' => ["def f():\n    def g():\n        pass\n", 'case.py:2: unsupported Python syntax FunctionDef: nested functions'],
            '同时变长与关键字变长' => ["def f(*a, **kw):\n    pass\n", 'case.py:1: unsupported Python syntax FunctionDef: simultaneous *args and **kwargs'],
            '链式比较' => ["x = 1 < 2 < 3\n", 'case.py:1: unsupported Python syntax Compare: chained comparisons'],
            'f-string 转换符' => ['x = 1' . "\n" . 'print(f"{x!r}")' . "\n", 'case.py:2: unsupported Python syntax FormattedValue'],
            'f-string 格式说明' => ['x = 1' . "\n" . 'print(f"{x:03d}")' . "\n", 'case.py:2: unsupported Python syntax FormattedValue'],
            'bytes 字面量' => ["x = b'abc'\n", 'case.py: Python bytes literals are not supported yet'],
            'complex 字面量' => ["x = 1j\n", 'case.py: Python complex literals are not supported yet'],
        ];
    }

    /** 模块别名不能作为一等值使用，错误格式与其他语法不同（无行号）。 */
    public function testModuleAliasCannotBeUsedAsValue(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('a Python module cannot be used as a first-class value');

        $this->convert("import sys\nx = sys\n");
    }
}
