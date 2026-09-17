--TEST--
Typed list/dict class values propagate through foreach and native method parameters
--FILE--
<?php
use StdList as ListOf;
class TypedUser
{
    public function __construct(public int $id) {}
    public function name(): string { return 'user'; }
}
class TypedReceiver
{
    public function fill(#[StdDict(Type::Str, TypedUser::class)] &$users): void
    {
        $users['123'] = new TypedUser(9);
        $users['alice'] = new TypedUser(7);
    }
}
function show(#[ListOf(TypedUser::class)] $users): void
{
    foreach ($users as $key => $user) {
        echo $key, ':', $user->id, ':', $user->name(), "\n";
    }
}
function main(): void
{
    $list = std::list(TypedUser::class);
    $list[] = new TypedUser(1);
    show($list);
    $dict = std::dict(Type::Str, TypedUser::class);
    $receiver = new TypedReceiver();
    $receiver->fill($dict);
    $copy = $dict;
    foreach ($dict as $name => $user) {
        var_dump(is_string($name), $name, $user->id);
    }
    var_dump($dict['123']->id, isset($dict['123']), empty($dict['123']));
    unset($dict['123']);
    var_dump(isset($dict['123']), isset($copy['123']), $copy['123']->id);
}
?>
--EXPECT--
0:1:user
bool(true)
string(3) "123"
int(9)
bool(true)
string(5) "alice"
int(7)
int(9)
bool(true)
bool(false)
bool(false)
bool(true)
int(9)
