--TEST--
PHP 8.4 promoted properties support asymmetric set visibility
--FILE--
<?php

class PromotedPrivateSet
{
    public function __construct(
        public private(set) string $name = 'initial',
    ) {
    }

    public function rename(string $name): void
    {
        $this->name = $name;
    }
}

class PromotedProtectedSet
{
    public function __construct(
        public protected(set) int $score = 0,
    ) {
    }

    public function setFromParent(int $score): void
    {
        $this->score = $score;
    }
}

class PromotedProtectedSetChild extends PromotedProtectedSet
{
    public function setFromChild(int $score): void
    {
        $this->score = $score;
    }
}

class PromotedImplicitPublicPrivateSet
{
    public function __construct(
        private(set) string $token,
    ) {
    }

    public function replace(string $token): void
    {
        $this->token = $token;
    }
}

#[Native]
class NativePromotedPrivateSet
{
    public function __construct(
        public private(set) int $value,
    ) {
    }

    public function increment(): void
    {
        $this->value++;
    }
}

#[Native]
class NativePromotedProtectedSet
{
    public function __construct(
        public protected(set) int $score,
    ) {
    }
}

#[Native]
class NativePromotedProtectedSetChild extends NativePromotedProtectedSet
{
    public function update(int $score): void
    {
        $this->score = $score;
    }
}

function rejectExternalWrites(mixed $private, mixed $protected): void
{
    try {
        $private->name = 'external';
    } catch (Error) {
        echo "private blocked\n";
    }
    try {
        $protected->score = 99;
    } catch (Error) {
        echo "protected blocked\n";
    }
}

function main(): void
{
    $defaultPrivate = new PromotedPrivateSet();
    var_dump($defaultPrivate->name);

    $private = new PromotedPrivateSet('constructor');
    var_dump($private->name);
    $private->rename('class');
    var_dump($private->name);

    $protected = new PromotedProtectedSetChild(10);
    $protected->setFromParent(20);
    $protected->setFromChild(30);
    var_dump($protected->score);

    $implicit = new PromotedImplicitPublicPrivateSet('implicit');
    var_dump($implicit->token);
    $implicit->replace('replaced');
    var_dump($implicit->token);

    $native = new NativePromotedPrivateSet(40);
    $native->increment();
    var_dump($native->value);

    $nativeChild = new NativePromotedProtectedSetChild(50);
    $nativeChild->update(51);
    var_dump($nativeChild->score);

    rejectExternalWrites($private, $protected);
    var_dump($private->name, $protected->score);

    $privateProperty = new ReflectionProperty(PromotedPrivateSet::class, 'name');
    var_dump(
        $privateProperty->isPromoted(),
        $privateProperty->isPrivateSet(),
        $privateProperty->isFinal(),
    );
    $protectedProperty = new ReflectionProperty(PromotedProtectedSet::class, 'score');
    var_dump(
        $protectedProperty->isPromoted(),
        $protectedProperty->isProtectedSet(),
        $protectedProperty->isFinal(),
    );
    $implicitProperty = new ReflectionProperty(PromotedImplicitPublicPrivateSet::class, 'token');
    var_dump(
        $implicitProperty->isPublic(),
        $implicitProperty->isPrivateSet(),
        $implicitProperty->isFinal(),
    );
}
?>
--EXPECT--
string(7) "initial"
string(11) "constructor"
string(5) "class"
int(30)
string(8) "implicit"
string(8) "replaced"
int(41)
int(51)
private blocked
protected blocked
string(5) "class"
int(30)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(false)
bool(true)
bool(true)
bool(true)
