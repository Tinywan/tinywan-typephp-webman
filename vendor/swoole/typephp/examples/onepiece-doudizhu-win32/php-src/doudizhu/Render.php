<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Games\OnePieceDoudizhu;

/**
 * Win32 rendering shim.
 *
 * The original GameController was written against libui's DrawContext /
 * Brush / Color / FontDescriptor API. Those symbols are re-declared here, in
 * the SAME namespace, so the controller's drawing code stays byte-for-byte
 * identical while the actual pixels are produced by the Win32 GDI primitives
 * declared in win32.stub.php (implemented in cpp-src/win32.cc).
 *
 * Colour convention: PHP code passes 0xRRGGBB ints. GDI needs 0xBBGGRR, so
 * ddz_rgb() flips the channels on the way down to C++.
 */

/* ----------------------------- constants ------------------------------ */

/** Text alignment — mirrors libui DrawTextAlign. */
final class DrawTextAlign
{
    public const Left = 0;
    public const Center = 1;
    public const Right = 2;
}

/** Font weight — mirrors libui TextWeight. */
final class TextWeight
{
    public const Normal = 400;
    public const Bold = 700;
}

/* ----------------------------- color types ---------------------------- */

final class Color
{
    public int $hex;

    public function __construct(int $hex)
    {
        $this->hex = $hex & 0xFFFFFF;
    }

    public static function rgb(int $hex): self
    {
        return new self($hex);
    }

    /** libui accepts normalised floats here (r,g,b in 0..1). */
    public static function rgba(float $r, float $g, float $b, float $a): self
    {
        $rr = (int) ($r * 255);
        $gg = (int) ($g * 255);
        $bb = (int) ($b * 255);

        return new self(($rr << 16) | ($gg << 8) | $bb);
    }
}

final class Brush
{
    public int $hex;

    public function __construct(int $hex)
    {
        $this->hex = $hex & 0xFFFFFF;
    }

    public static function rgb(int $hex): self
    {
        return new self($hex);
    }

    /** Extract the int colour from a Color (or pass-through an int brush). */
    public static function color($c): int
    {
        if ($c instanceof Color) {
            return $c->hex;
        }
        if ($c instanceof Brush) {
            return $c->hex;
        }

        return (int) $c;
    }

    /**
     * Gradients are approximated by a solid fill in this GDI port.
     *
     * Signature mirrors libui's brushForFill linearGradient: four float
     * coordinates plus a list of [offset, r, g, b, a] stops. We take the
     * colour of the LAST stop (or the deep-navy fallback) as the flat fill.
     *
     * @param array<int, array{0: float, 1: float, 2: float, 3: float, 4?: float}> $stops
     */
    public static function linearGradient(float $x0, float $y0, float $x1, float $y1, array $stops): self
    {
        $hex = 0x08152e;
        if (\count($stops) > 0) {
            $last = $stops[\count($stops) - 1];
            $r = (int) ($last[1] * 255);
            $g = (int) ($last[2] * 255);
            $b = (int) ($last[3] * 255);
            $hex = ($r << 16) | ($g << 8) | $b;
        }

        return new self($hex);
    }
}

final class StrokeParams
{
    public int $thickness = 1;

    public function thickness(int $t): self
    {
        $this->thickness = $t;

        return $this;
    }
}

final class FontDescriptor
{
    public string $family;
    public int $size;
    public int $weight;

    public function __construct(string $family, int $size, int $weight = TextWeight::Normal)
    {
        $this->family = $family;
        $this->size = $size;
        $this->weight = $weight;
    }
}

/* ----------------------------- helpers -------------------------------- */

/** 0xRRGGBB -> Win32 COLORREF (0xBBGGRR). */
function ddz_rgb(int $hex): int
{
    $r = ($hex >> 16) & 0xFF;
    $g = ($hex >> 8) & 0xFF;
    $b = $hex & 0xFF;

    return ($r) | ($g << 8) | ($b << 16);
}

/* ------------------- UTF-8 helpers (no mbstring) ---------------------- */

/**
 * Character (code point) length of a UTF-8 string.
 * mbstring is not linked into the TypePHP runtime, so we count code points
 * with PCRE instead of mb_strlen().
 */
function ddz_utf8_len(string $s): int
{
    if ($s === '') {
        return 0;
    }

    return \preg_match_all('/./us', $s);
}

/**
 * Safe UTF-8 substring by code points (no mbstring).
 * Negative $start/$len behave like mb_substr() (offsets from the end).
 */
function ddz_utf8_substr(string $s, int $start, int $len = 0): string
{
    if ($s === '') {
        return '';
    }
    \preg_match_all('/./us', $s, $m);
    $chars = $m[0];
    $n = \count($chars);
    if ($len === 0) {
        $len = $n;
    }
    if ($start < 0) {
        $start = \max(0, $n + $start);
    }
    if ($len < 0) {
        $len = \max(0, $n - $start + $len);
    }
    $slice = \array_slice($chars, $start, $len);

    return \implode('', $slice);
}

/* --------------------------- draw context ----------------------------- */

/**
 * Mimics libui's DrawContext but renders through Win32 GDI. The single
 * constructor argument is the memory-DC handle produced by win_begin_paint().
 */
final class WinDrawContext
{
    private int $hdc;

    public function __construct(int $hdc)
    {
        $this->hdc = $hdc;
    }

    private function colOf($b): int
    {
        if ($b instanceof Color || $b instanceof Brush) {
            return $b->hex;
        }

        return (int) $b;
    }

    public function fillRect(float $x, float $y, float $w, float $h, $brush): void
    {
        win_fill_rect($this->hdc, (int) $x, (int) $y, (int) $w, (int) $h, ddz_rgb($this->colOf($brush)));
    }

    /** Ellipse centred at (cx, cy) — matches libui semantics. */
    public function fillEllipse(float $cx, float $cy, float $w, float $h, $brush): void
    {
        win_fill_ellipse($this->hdc, (int) $cx, (int) $cy, (int) $w, (int) $h, ddz_rgb($this->colOf($brush)));
    }

    public function fillRoundedRect(float $x, float $y, float $w, float $h, float $r, $brush): void
    {
        win_fill_rounded_rect($this->hdc, (int) $x, (int) $y, (int) $w, (int) $h, (int) $r, ddz_rgb($this->colOf($brush)));
    }

    public function strokeRoundedRect(float $x, float $y, float $w, float $h, float $r, $brush, StrokeParams $stroke): void
    {
        win_stroke_rounded_rect(
            $this->hdc,
            (int) $x, (int) $y, (int) $w, (int) $h, (int) $r,
            ddz_rgb($this->colOf($brush)),
            (int) $stroke->thickness
        );
    }

    /**
     * @param int $w     width of the alignment box (0 = no alignment)
     * @param int $align DrawTextAlign::Left|Center|Right
     */
    public function drawString(string $text, FontDescriptor $font, Color $color, float $x, float $y, int $w = 0, int $align = DrawTextAlign::Left): void
    {
        win_draw_text_ex(
            $this->hdc,
            (int) $x, (int) $y, $text,
            (int) $font->size,
            ddz_rgb($color->hex),
            $font->weight >= TextWeight::Bold ? 1 : 0,
            (int) $w,
            (int) $align
        );
    }
}
