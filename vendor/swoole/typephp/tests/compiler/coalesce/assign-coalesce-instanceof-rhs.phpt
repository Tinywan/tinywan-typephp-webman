--TEST--
null coalescing assignment with instanceof boolean expression rhs
--FILE--
<?php

class CoalescePlatform
{
    public function enabled(): bool
    {
        echo "enabled\n";
        return true;
    }
}

function ensure_option(array &$options, object $platform): void
{
    $options['is_zts'] ??= $platform instanceof CoalescePlatform && $platform->enabled();
}

function main(): void
{
    $options = [];
    ensure_option($options, new CoalescePlatform());
    var_dump($options);

    ensure_option($options, new CoalescePlatform());
    var_dump($options);

    $options = [];
    ensure_option($options, new stdClass());
    var_dump($options);
}
?>
--EXPECT--
enabled
array(1) {
  ["is_zts"]=>
  bool(true)
}
array(1) {
  ["is_zts"]=>
  bool(true)
}
array(1) {
  ["is_zts"]=>
  bool(false)
}
