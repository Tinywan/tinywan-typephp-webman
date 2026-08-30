--TEST--
Symfony style dynamic instanceof against pipe-separated type string
--FILE--
<?php
interface UserInterface {}
class AdminUser implements UserInterface {}
class GuestUser {}

function resolveUser(object $user, ?string $type): array
{
    if (null === $type || $user instanceof ($type)) {
        return [$user::class];
    }

    $types = explode('|', $type);
    foreach ($types as $candidate) {
        if ($user instanceof $candidate) {
            return [$user::class, $candidate];
        }
    }

    return [];
}

function main(): void
{
    var_dump(resolveUser(new AdminUser(), UserInterface::class));
    var_dump(resolveUser(new AdminUser(), GuestUser::class.'|'.UserInterface::class));
    var_dump(resolveUser(new GuestUser(), UserInterface::class));
}
?>
--EXPECT--
array(1) {
  [0]=>
  string(9) "AdminUser"
}
array(2) {
  [0]=>
  string(9) "AdminUser"
  [1]=>
  string(13) "UserInterface"
}
array(0) {
}
