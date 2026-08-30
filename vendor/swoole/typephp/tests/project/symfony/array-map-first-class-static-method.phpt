--TEST--
Symfony pattern: array_map with first-class static method callable
--FILE--
<?php

class SymfonyLikeScheduleRenderer
{
    public static function render(string $message, DateTimeImmutable $date, bool $all): ?array
    {
        if (!$all && str_starts_with($message, 'skip')) {
            return null;
        }

        return [$message, $date->format('Y-m-d'), $all];
    }

    public static function renderAll(array $messages, DateTimeImmutable $date, bool $all): array
    {
        return array_filter(array_map(
            self::render(...),
            $messages,
            array_fill(0, count($messages), $date),
            array_fill(0, count($messages), $all)
        ));
    }
}

function main(): void
{
    var_dump(SymfonyLikeScheduleRenderer::renderAll(['first', 'skip-second'], new DateTimeImmutable('2026-07-07'), false));
}
?>
--EXPECT--
array(1) {
  [0]=>
  array(3) {
    [0]=>
    string(5) "first"
    [1]=>
    string(10) "2026-07-07"
    [2]=>
    bool(false)
  }
}
