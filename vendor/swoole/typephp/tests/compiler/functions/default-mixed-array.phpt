--TEST--
mixed array default args
--FILE--
<?php

function render_items(array $items = [1 => 'yes', 'next' => 'no', 2 => 'maybe']): string
{
    return implode(', ', $items);
}

class Demo
{
    public static function render(array $items = [1 => 'yes', 'next' => 'no', 2 => 'maybe']): string
    {
        return implode(', ', $items);
    }
}

function main(): void
{
    echo render_items() . "\n";
    echo Demo::render() . "\n";
}
?>
--EXPECT--
yes, no, maybe
yes, no, maybe
