--TEST--
ThinkPHP Pipeline pattern: array_reduce nested closures with exception handler
--XFAIL--
Known AOT bug: method using func_get_args() with explicit arguments can hit arginfo/zpp mismatch.
--FILE--
<?php

class ThinkPipelineLike
{
    private mixed $passable = null;
    private array $pipes = [];
    private mixed $exceptionHandler = null;

    public function send(mixed $passable): static
    {
        $this->passable = $passable;
        return $this;
    }

    public function through(mixed $pipes): static
    {
        $this->pipes = is_array($pipes) ? $pipes : func_get_args();
        return $this;
    }

    public function whenException(callable $handler): static
    {
        $this->exceptionHandler = $handler;
        return $this;
    }

    public function then(Closure $destination): mixed
    {
        $pipeline = array_reduce(
            array_reverse($this->pipes),
            $this->carry(),
            function ($passable) use ($destination) {
                try {
                    return $destination($passable);
                } catch (Throwable | Exception $e) {
                    return $this->handleException($passable, $e);
                }
            }
        );

        return $pipeline($this->passable);
    }

    private function carry(): Closure
    {
        return function ($stack, $pipe) {
            return function ($passable) use ($stack, $pipe) {
                try {
                    return $pipe($passable, $stack);
                } catch (Throwable | Exception $e) {
                    return $this->handleException($passable, $e);
                }
            };
        };
    }

    private function handleException(mixed $passable, Throwable $e): mixed
    {
        if ($this->exceptionHandler) {
            return call_user_func($this->exceptionHandler, $passable, $e);
        }
        throw $e;
    }
}

function main(): void
{
    $pipeline = new ThinkPipelineLike();
    $result = $pipeline
        ->send('start')
        ->through(
            fn ($value, $next) => $next($value . ':a') . ':after-a',
            function ($value, $next) {
                throw new RuntimeException($value . ':boom');
            },
        )
        ->whenException(fn ($value, Throwable $e) => $value . ':' . $e->getMessage())
        ->then(fn ($value) => $value . ':done');

    var_dump($result);
}
?>
--EXPECT--
string(27) "start:a:start:a:boom:after-a"
