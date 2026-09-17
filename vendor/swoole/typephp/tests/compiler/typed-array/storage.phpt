--TEST--
Typed PHP arrays retain COW storage, native references, sparse keys and foreach types
--FILE--
<?php
function copy_list(#[StdList(Type::Int)] $list): void
{
    $list[] = 99;
}
function append_list(#[StdList(Type::Int)] &$list): void
{
    $list[] = 42;
}
function main(): void
{
    $list = std::list(Type::Int);
    $list[-3] = 5;
    $list[100] = 7;
    $list[] = 8;
    $copy = $list;
    $alias =& $list;
    $alias2 =& $alias;
    $alias2[100] = 9;
    copy_list($list);
    append_list(list: $alias);
    var_dump(is_array($list), count($list), $list[100], $copy[100]);
    foreach ($list as $key => $value) {
        echo $key, ':', $value, "\n";
    }
    $dict = std::dict(Type::Int, Type::Str);
    $dict[-10] = 'minus';
    $dict[1000] = 'sparse';
    foreach ($dict as $id => $name) {
        echo $id, ':', $name, "\n";
    }
    unset($list[100]);
    var_dump(isset($list[100]), empty($list[100]), count($alias));
    var_dump(array_search(8, $list), array_sum($list));
    $visit = function() use ($list): int {
        $list[] = 70;
        return count($list);
    };
    var_dump($visit(), count($list));
}
?>
--EXPECT--
bool(true)
int(4)
int(9)
int(7)
-3:5
100:9
101:8
102:42
-10:minus
1000:sparse
bool(false)
bool(true)
int(3)
int(101)
int(55)
int(4)
int(3)
