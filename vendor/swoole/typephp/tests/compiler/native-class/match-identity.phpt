--TEST--
Native class: match compares object identity without entering ZendVM
--FILE--
<?php

#[Native]
class NativeMatchValue {}

#[Native]
class NativeMatchUnrelated {}

function nativeMatchIdentity(NativeMatchValue $value): NativeMatchValue
{
    echo "subject\n";
    return $value;
}

function unrelatedMatchValue(): NativeMatchUnrelated
{
    echo "unrelated\n";
    return new NativeMatchUnrelated();
}

function scalarMatchValue(): int
{
    echo "scalar\n";
    return 42;
}

function temporaryMatchSubject(): NativeMatchValue
{
    return new NativeMatchValue();
}

function pressuredMatchValue(): NativeMatchValue
{
    for ($i = 0; $i < 300000; $i++) {
        $filler = new NativeMatchValue();
    }
    return new NativeMatchValue();
}

function choose(?NativeMatchValue $subject, NativeMatchValue $same, NativeMatchValue $other): string
{
    return match ($subject) {
        $other => 'other',
        $same => 'same',
        null => 'null',
    };
}

function main(): void
{
    $value = new NativeMatchValue();
    $other = new NativeMatchValue();

    var_dump(choose($value, $value, $other));
    var_dump(choose(null, $value, $other));
    var_dump(match (nativeMatchIdentity($value)) {
        unrelatedMatchValue() => 'unrelated',
        scalarMatchValue() => 'scalar',
        $value => 'identity',
    });
    var_dump(match (temporaryMatchSubject()) {
        pressuredMatchValue() => 'reused',
        default => 'distinct',
    });
}

?>
--EXPECT--
string(4) "same"
string(4) "null"
subject
unrelated
scalar
string(8) "identity"
string(8) "distinct"
