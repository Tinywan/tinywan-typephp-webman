--TEST--
Symfony HtmlSanitizer pattern: array visitor lookup with nullsafe render fallback
--FILE--
<?php

final class RenderedNode
{
    public function __construct(private string $html)
    {
    }

    public function render(): string
    {
        return $this->html;
    }
}

final class Visitor
{
    public function __construct(private ?RenderedNode $node)
    {
    }

    public function visit(object $parsed): ?RenderedNode
    {
        return $this->node;
    }
}

function render_sanitized(array $domVisitors, string $context, ?object $parsed): string
{
    if (null === $parsed) {
        return '';
    }

    return $domVisitors[$context]->visit($parsed)?->render() ?? '';
}

function main(): void
{
    $visitors = [
        'body' => new Visitor(new RenderedNode('<p>ok</p>')),
        'head' => new Visitor(null),
    ];

    var_dump(render_sanitized($visitors, 'body', new stdClass()));
    var_dump(render_sanitized($visitors, 'head', new stdClass()));
    var_dump(render_sanitized($visitors, 'body', null));
}
?>
--EXPECT--
string(9) "<p>ok</p>"
string(0) ""
string(0) ""
