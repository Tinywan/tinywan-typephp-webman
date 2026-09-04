--TEST--
Object property reference assignment preserves aliases and typed-property sources
--FILE--
<?php
declare(strict_types=1);

final class PropertyReferenceHolder
{
    public ?array $value = null;
}

final class PrivatePropertyReferenceHolder
{
    private ?array $value = null;

    public function bind(array &$source): void
    {
        $this->value = &$source;
    }

    public function value(): ?array
    {
        return $this->value;
    }
}

final class PropertyReferenceDep
{
    public ?array $map = null;
}

function bindObjectProperty(object $holder, mixed &$source): void
{
    $holder->value = &$source;
}

function replaceReference(mixed &$target, mixed $value): void
{
    $target = $value;
}

function propertyReferenceTarget(array &$events, object $holder): object
{
    $events[] = 'object';
    return $holder;
}

function propertyReferenceName(array &$events): string
{
    $events[] = 'property';
    return 'value';
}

function &propertyReferenceSource(array &$events, mixed &$source): mixed
{
    $events[] = 'source';
    return $source;
}

function trackThroughSplObjectStorage(object $target): PropertyReferenceDep
{
    $storage = new SplObjectStorage();
    $storage->offsetSet($target, []);
    $map = $storage[$target];

    $dep = new PropertyReferenceDep();
    $dep->map = &$map;
    $map['dep'] = $dep;
    $storage->offsetSet($target, $map);
    return $dep;
}

function main(): void
{
    $holder = new PropertyReferenceHolder();
    $source = ['initial' => 1];
    $holder->value = &$source;
    $source['source'] = 2;
    $holder->value['property'] = 3;
    var_dump($source);

    $dynamicHolder = new PropertyReferenceHolder();
    $dynamicSource = [];
    bindObjectProperty($dynamicHolder, $dynamicSource);
    $dynamicSource['dynamic'] = true;
    var_dump($dynamicHolder->value);

    $wrong = 'invalid';
    try {
        bindObjectProperty($dynamicHolder, $wrong);
        echo "missing initial TypeError\n";
    } catch (TypeError $error) {
        echo "initial TypeError\n";
    }
    $dynamicSource['preserved'] = true;
    var_dump($dynamicHolder->value);

    $replacement = ['replacement' => true];
    $holder->value = &$replacement;
    replaceReference($source, 'detached');
    var_dump($source);
    try {
        replaceReference($replacement, 'invalid');
        echo "missing write TypeError\n";
    } catch (TypeError $error) {
        echo "write TypeError\n";
    }
    var_dump($holder->value);

    $privateSource = [];
    $privateHolder = new PrivatePropertyReferenceHolder();
    $privateHolder->bind($privateSource);
    $privateSource['private'] = true;
    var_dump($privateHolder->value());

    $events = [];
    $orderedSource = [];
    $orderedHolder = new PropertyReferenceHolder();
    propertyReferenceTarget($events, $orderedHolder)->{propertyReferenceName($events)}
        = &propertyReferenceSource($events, $orderedSource);
    $orderedSource['ordered'] = true;
    var_dump($events, $orderedHolder->value);

    $dep = trackThroughSplObjectStorage(new stdClass());
    var_dump(isset($dep->map['dep']));
}
?>
--EXPECT--
array(3) {
  ["initial"]=>
  int(1)
  ["source"]=>
  int(2)
  ["property"]=>
  int(3)
}
array(1) {
  ["dynamic"]=>
  bool(true)
}
initial TypeError
array(2) {
  ["dynamic"]=>
  bool(true)
  ["preserved"]=>
  bool(true)
}
string(8) "detached"
write TypeError
array(1) {
  ["replacement"]=>
  bool(true)
}
array(1) {
  ["private"]=>
  bool(true)
}
array(3) {
  [0]=>
  string(6) "object"
  [1]=>
  string(8) "property"
  [2]=>
  string(6) "source"
}
array(1) {
  ["ordered"]=>
  bool(true)
}
bool(true)
