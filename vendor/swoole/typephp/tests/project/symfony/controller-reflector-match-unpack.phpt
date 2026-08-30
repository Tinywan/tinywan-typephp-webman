--TEST--
Symfony HttpKernel pattern: controller reflector match with unpacked callable
--FILE--
<?php

class DemoController
{
    public function action(): void
    {
    }

    public static function staticAction(): void
    {
    }
}

function reflectorOf(callable|string $controller): ReflectionFunctionAbstract
{
    return match (true) {
        is_array($controller) && method_exists(...$controller) => new ReflectionMethod(...$controller),
        is_string($controller) && str_contains($controller, '::') => new ReflectionMethod(...explode('::', $controller, 2)),
        default => new ReflectionFunction($controller(...)),
    };
}

function main(): void
{
    $object = new DemoController();

    var_dump(reflectorOf([$object, 'action'])->getName());
    var_dump(reflectorOf(DemoController::class.'::staticAction')->getName());
    var_dump(reflectorOf(static fn () => null)->isClosure());
}
?>
--EXPECT--
string(6) "action"
string(12) "staticAction"
bool(true)
