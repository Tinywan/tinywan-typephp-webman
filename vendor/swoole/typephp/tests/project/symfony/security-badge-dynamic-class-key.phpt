--TEST--
Symfony Security style dynamic $badge::class key with coalesce assignment
--FILE--
<?php
interface SymfonyBadgeInterface
{
    public function name(): string;
}

class SymfonyCsrfBadge implements SymfonyBadgeInterface
{
    public function name(): string
    {
        return 'csrf';
    }
}

class SymfonyUserBadge implements SymfonyBadgeInterface
{
    public function name(): string
    {
        return 'user';
    }
}

class SymfonyPassportCase
{
    private array $badges = [];

    public function addBadge(SymfonyBadgeInterface $badge, ?string $badgeFqcn = null): static
    {
        $badgeFqcn ??= $badge::class;

        $this->badges[$badgeFqcn] = $badge;

        return $this;
    }

    public function hasBadge(string $badgeFqcn): bool
    {
        return isset($this->badges[$badgeFqcn]);
    }

    public function names(): array
    {
        return array_map(static fn (SymfonyBadgeInterface $badge): string => $badge->name(), $this->badges);
    }
}

function main(): void
{
    $passport = new SymfonyPassportCase();
    $passport
        ->addBadge(new SymfonyCsrfBadge())
        ->addBadge(new SymfonyUserBadge(), 'custom.user');

    var_dump($passport->hasBadge(SymfonyCsrfBadge::class));
    var_dump($passport->hasBadge(SymfonyUserBadge::class));
    var_dump($passport->hasBadge('custom.user'));
    var_dump($passport->names());
}
?>
--EXPECT--
bool(true)
bool(false)
bool(true)
array(2) {
  ["SymfonyCsrfBadge"]=>
  string(4) "csrf"
  ["custom.user"]=>
  string(4) "user"
}
