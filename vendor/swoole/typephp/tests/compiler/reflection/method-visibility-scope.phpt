--TEST--
Reflection invocation preserves AOT method visibility scope
--FILE--
<?php

class ReflectionVisibilityBox
{
    protected function hidden(): string
    {
        return 'protected-ok';
    }

    protected static function decorate(string $value): string
    {
        return "[{$value}]";
    }

    public static function create(): string
    {
        $box = new static();
        return $box->hidden();
    }

    public static function mapProtected(): array
    {
        return array_map([self::class, 'decorate'], ['callback']);
    }

    public static function invokeProtected(): string
    {
        $method = new ReflectionMethod(self::class, 'decorate');
        return $method->invoke(null, 'reflection');
    }

    public static function invokeProtectedArgs(): string
    {
        $method = new ReflectionMethod(self::class, 'decorate');
        return $method->invokeArgs(null, ['reflection-args']);
    }
}

function main(): void
{
    $factory = new ReflectionMethod(ReflectionVisibilityBox::class, 'create');
    var_dump($factory->invoke(null));

    $hidden = new ReflectionMethod(ReflectionVisibilityBox::class, 'hidden');
    $hidden->setAccessible(true);
    var_dump($hidden->invoke(new ReflectionVisibilityBox()));

    var_dump(ReflectionVisibilityBox::mapProtected());
    var_dump(ReflectionVisibilityBox::invokeProtected());
    var_dump(ReflectionVisibilityBox::invokeProtectedArgs());
}

?>
--EXPECT--
string(12) "protected-ok"
string(12) "protected-ok"
array(1) {
  [0]=>
  string(10) "[callback]"
}
string(12) "[reflection]"
string(17) "[reflection-args]"
