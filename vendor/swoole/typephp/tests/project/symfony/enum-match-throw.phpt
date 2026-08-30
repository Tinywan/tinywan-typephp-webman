--TEST--
Symfony pattern: enum methods with match arms and throw expression
--FILE--
<?php

enum SymfonyLikeColorMode
{
    case Ansi4;
    case Ansi8;
    case Ansi24;

    public function convertFromHexToAnsiColorCode(string $hexColor): string
    {
        $hexColor = str_replace('#', '', $hexColor);

        if (3 === strlen($hexColor)) {
            $hexColor = $hexColor[0].$hexColor[0].$hexColor[1].$hexColor[1].$hexColor[2].$hexColor[2];
        }

        $color = hexdec($hexColor);
        $r = ($color >> 16) & 255;
        $g = ($color >> 8) & 255;
        $b = $color & 255;

        return match ($this) {
            self::Ansi4 => (string) $this->convertFromRGB($r, $g, $b),
            self::Ansi8 => '8;5;'.$this->convertFromRGB($r, $g, $b),
            self::Ansi24 => sprintf('8;2;%d;%d;%d', $r, $g, $b),
        };
    }

    public function convertFromRGB(int $r, int $g, int $b): int
    {
        return match ($this) {
            self::Ansi4 => (round($b / 255) << 2) | (round($g / 255) << 1) | round($r / 255),
            self::Ansi8 => 16 + 36 * (int) round($r / 255 * 5) + 6 * (int) round($g / 255 * 5) + (int) round($b / 255 * 5),
            default => throw new InvalidArgumentException("RGB cannot be converted to {$this->name}."),
        };
    }
}

function main(): void
{
    var_dump(SymfonyLikeColorMode::Ansi4->convertFromHexToAnsiColorCode('#fff'));
    var_dump(SymfonyLikeColorMode::Ansi8->convertFromHexToAnsiColorCode('#000'));
    var_dump(SymfonyLikeColorMode::Ansi24->convertFromHexToAnsiColorCode('#123456'));

    try {
        SymfonyLikeColorMode::Ansi24->convertFromRGB(1, 2, 3);
    } catch (Throwable $e) {
        var_dump($e->getMessage());
    }
}
?>
--EXPECT--
string(1) "7"
string(6) "8;5;16"
string(12) "8;2;18;52;86"
string(34) "RGB cannot be converted to Ansi24."
