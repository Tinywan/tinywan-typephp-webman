--TEST--
Symfony Finder pattern: object method first-class callable with array_merge unpack
--FILE--
<?php
class SymfonyFinderDirNormalizer
{
    private array $dirs = [];

    public function in(array|string $dirs): self
    {
        $resolvedDirs = [];
        foreach ((array) $dirs as $dir) {
            $glob = str_contains($dir, '*') ? ['src/', 'tests/'] : [$dir];
            $resolvedDirs[] = array_map($this->normalizeDir(...), $glob);
        }

        $this->dirs = array_merge($this->dirs, ...$resolvedDirs);

        return $this;
    }

    private function normalizeDir(string $dir): string
    {
        return rtrim(str_replace('\\', '/', $dir), '/').'/';
    }

    public function getDirs(): array
    {
        return $this->dirs;
    }
}

function main(): void
{
    $finder = (new SymfonyFinderDirNormalizer())
        ->in('var/cache')
        ->in(['app/*', 'vendor/package']);

    var_dump($finder->getDirs());
}
?>
--EXPECT--
array(4) {
  [0]=>
  string(10) "var/cache/"
  [1]=>
  string(4) "src/"
  [2]=>
  string(6) "tests/"
  [3]=>
  string(15) "vendor/package/"
}
