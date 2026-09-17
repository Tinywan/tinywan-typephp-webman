--TEST--
Std attributes accept matching array/box storage types and sparse property list writes
--FILE--
<?php
class PropertyState
{
    #[StdList(Type::Int)] public array $values = [];
    #[StdDict(Type::Str, Type::Int)] public array $counts = [];
    #[StdList(Type::Str)] public $names = [];
    #[StdDict(Type::Int, Type::Str)] public static array $labels = [];
    #[StdVector(Type::Int)] public box $vector;
}
#[Native]
class NativePropertyState
{
    #[StdList(Type::Int)] public $values = [];
}
function append_vector(#[StdVector(Type::Int)] box $v): void { $v[] = 5; }
function write_map(#[StdMap(Type::Str, Type::Int)] box $m): void { $m['a'] = 6; }
function write_ordered(#[StdOrderedMap(Type::Int, Type::Str)] box $m): void { $m[1] = 'one'; }
function copy_list(#[StdList(Type::Int)] array $a): void { $a[] = 7; }
function append_list(#[StdList(Type::Int)] array &$a): void { $a[] = 8; }
function write_dict(#[StdDict(Type::Str, Type::Int)] array &$a): void { $a['a'] = 9; }
function write_property(PropertyState $s, $key): void { $s->values[$key] = 40; }
function main(): void
{
    $v = std::vector(Type::Int);
    append_vector($v);
    echo $v[0], "\n";
    $m = std::map(Type::Str, Type::Int);
    write_map($m);
    echo $m['a'], "\n";
    $o = std::orderedMap(Type::Int, Type::Str);
    write_ordered($o);
    echo $o[1], "\n";
    $a = std::list(Type::Int);
    copy_list($a);
    append_list($a);
    echo count($a), ':', $a[0], "\n";
    $d = std::dict(Type::Str, Type::Int);
    write_dict($d);
    echo $d['a'], "\n";
    $s = new PropertyState();
    $s->values[-5] = 10;
    $s->values[100] = 20;
    $s->values[] = 30;
    write_property($s, 200);
    echo $s->values[-5], ':', $s->values[100], ':', $s->values[101], ':', $s->values[200], "\n";
    try { write_property($s, '200'); }
    catch (TypeError $error) { echo "strict property key\n"; }
    $s->counts['123'] = 2;
    $s->names[100] = 'sparse';
    PropertyState::$labels[-3] = 'negative';
    echo $s->counts['123'], ':', $s->names[100], ':', PropertyState::$labels[-3], "\n";
    $n = new NativePropertyState();
    $n->values[-2] = 11;
    $n->values[100] = 12;
    $n->values[] = 13;
    echo $n->values[-2], ':', $n->values[100], ':', $n->values[101], "\n";
}
?>
--EXPECT--
5
6
one
1:8
9
10:20:30:40
strict property key
2:sparse:negative
11:12:13
