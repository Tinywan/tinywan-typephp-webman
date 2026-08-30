--TEST--
ThinkPHP Middleware pattern: array_map nested closure and unpacked callable params
--FILE--
<?php

class ThinkMiddlewareLike
{
    private array $queue = [];

    public function add(array|Closure|string $middleware): void
    {
        $this->queue[] = $this->buildMiddleware($middleware);
    }

    private function buildMiddleware(array|Closure|string $middleware): array
    {
        if (is_array($middleware)) {
            [$middleware, $params] = $middleware;
        }

        if ($middleware instanceof Closure) {
            return [$middleware, $params ?? []];
        }

        return [[$middleware, 'handle'], $params ?? []];
    }

    public function pipeline(): array
    {
        return array_map(function ($middleware) {
            return function ($request, $next) use ($middleware) {
                [$call, $params] = $middleware;
                $response = call_user_func($call, $request, $next, ...$params);
                if (!$response instanceof ThinkResponseLike) {
                    throw new LogicException('The middleware must return Response instance');
                }
                return $response;
            };
        }, $this->queue);
    }
}

class ThinkResponseLike
{
    public function __construct(public string $body)
    {
    }
}

function main(): void
{
    $middleware = new ThinkMiddlewareLike();
    $middleware->add([
        function ($request, $next, string $prefix, string $suffix): ThinkResponseLike {
            $response = $next($prefix . $request);
            $response->body .= $suffix;
            return $response;
        },
        ['[', ']'],
    ]);

    $pipes = $middleware->pipeline();
    $response = $pipes[0]('thinkphp', fn ($request) => new ThinkResponseLike($request . ':next'));

    var_dump($response->body);
}
?>
--EXPECT--
string(15) "[thinkphp:next]"
