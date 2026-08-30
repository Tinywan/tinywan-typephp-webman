--TEST--
ThinkPHP File pattern: match true with assignment and callable branch
--FILE--
<?php

class ThinkFileLike
{
    private ?string $hashName = null;

    public function __construct(private string $path)
    {
    }

    public function hash(string $algo): string
    {
        return hash($algo, $this->path);
    }

    public function getPathname(): string
    {
        return $this->path;
    }

    public function hashName(string|Closure|null $rule = null): string
    {
        if (!$this->hashName) {
            if ($rule instanceof Closure) {
                $this->hashName = call_user_func_array($rule, [$this]);
            } else {
                $this->hashName = match (true) {
                    in_array($rule, hash_algos(), true) && $hash = $this->hash($rule) => substr($hash, 0, 2) . '/' . substr($hash, 2),
                    is_callable($rule) => call_user_func($rule),
                    default => 'date/' . md5($this->getPathname()),
                };
            }
        }

        return $this->hashName;
    }
}

function main(): void
{
    $file = new ThinkFileLike('thinkphp');
    var_dump($file->hashName('md5'));

    $file = new ThinkFileLike('thinkphp');
    var_dump($file->hashName(fn (ThinkFileLike $f) => 'closure:' . basename($f->getPathname())));

    $file = new ThinkFileLike('thinkphp');
    var_dump($file->hashName('phpversion'));
}
?>
--EXPECTF--
string(33) "%s/%s"
string(16) "closure:thinkphp"
string(%d) "%s"
