--TEST--
Symfony Validator Constraint pattern: serialize object vars with match(true) private key normalization
--XFAIL--
AOT does not yet match private object array keys with NUL-prefixed class names in str_starts_with().
--FILE--
<?php
class SymfonyConstraintLike
{
    public string $publicName = 'public';
    protected string $protectedName = 'protected';
    private string $privateName = 'private';

    public function __serialize(): array
    {
        $data = [];
        $class = $this::class;
        foreach ((array) $this as $k => $v) {
            $data[match (true) {
                '' === $k || "\0" !== $k[0] => $k,
                str_starts_with($k, "\0*\0") => substr($k, 3),
                str_starts_with($k, "\0{$class}\0") => substr($k, 2 + strlen($class)),
                default => $k,
            }] = $v;
        }

        return $data;
    }
}

function main(): void
{
    var_dump((new SymfonyConstraintLike())->__serialize());
}
?>
--EXPECT--
array(3) {
  ["publicName"]=>
  string(6) "public"
  ["protectedName"]=>
  string(9) "protected"
  ["privateName"]=>
  string(7) "private"
}
