--TEST--
Enum cases use Zend enum handlers and retain case attributes
--FILE--
<?php

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
final class Marker
{
    public function __construct(public string $name) {}
}

enum Suit
{
    /** @genstubs-expose-comment-block
     * Hearts documentation.
     */
    #[Marker('hearts')]
    case Hearts;
    case Spades;
}

final class Holder
{
    public UnitEnum $case = Suit::Hearts;
}

function main(): void
{
    try {
        clone Suit::Hearts;
    } catch (Error $error) {
        echo $error->getMessage(), "\n";
    }

    var_dump(Suit::Hearts < Suit::Spades);
    var_dump(Suit::Hearts <=> Suit::Spades);

    $case = new ReflectionEnumUnitCase(Suit::class, 'Hearts');
    $attributes = $case->getAttributes(Marker::class);
    var_dump(count($attributes));
    var_dump($attributes[0]->newInstance()->name);
    var_dump(str_contains($case->getDocComment(), 'Hearts documentation.'));
    var_dump((new Holder())->case === Suit::Hearts);
}
?>
--EXPECT--
Trying to clone an uncloneable object of class Suit
bool(false)
int(1)
int(1)
string(6) "hearts"
bool(true)
bool(true)
