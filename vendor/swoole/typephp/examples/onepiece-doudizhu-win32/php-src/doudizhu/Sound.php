<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Games\OnePieceDoudizhu;

/**
 * Sound-effects manager (TypePHP / Win32 port).
 *
 * The original relied on procedural WAV files loaded through the
 * Yangweijie\Ui2\System\Audio (miniaudio) bridge, which is not available in a
 * statically-compiled TypePHP binary. This port keeps the exact same public
 * API used by GameController (instance / trigger / setEnabled / unload …) but
 * is a safe no-op, so the game logic and event hooks are unchanged.
 *
 * To add real audio later, generate assets/audio/*.wav and call
 * win_message_beep() (or a miniaudio bridge) from trigger().
 */
final class Sound
{
    public const CLICK = 'click';
    public const DEAL = 'deal';
    public const PLAY = 'play';
    public const PASS = 'pass';
    public const BOMB = 'bomb';
    public const SKILL = 'skill';
    public const BID = 'bid';
    public const WIN = 'win';
    public const LOSE = 'lose';

    private const DEFAULT_BINDINGS = [
        'click' => self::CLICK,
        'deal' => self::DEAL,
        'play' => self::PLAY,
        'pass' => self::PASS,
        'bomb' => self::BOMB,
        'skill' => self::SKILL,
        'bid' => self::BID,
        'win' => self::WIN,
        'lose' => self::LOSE,
    ];

    private static ?self $instance = null;

    private array $bindings;
    private float $volume = 0.8;
    private bool $enabled = true;

    public function __construct()
    {
        $this->bindings = self::DEFAULT_BINDINGS;
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function setVolume(float $v): self
    {
        $this->volume = max(0.0, min(1.0, $v));

        return $this;
    }

    public function setEnabled(bool $on): self
    {
        $this->enabled = $on;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function bind(string $event, string $sound): self
    {
        $this->bindings[$event] = $sound;

        return $this;
    }

    /** Play a sound by name. No-op in this port. */
    public function play(string $name): void
    {
        // Intentionally silent: no audio assets in the compiled binary.
    }

    /** Fire a named game event; plays the bound sound if any. */
    public function trigger(string $event): void
    {
        $sound = $this->bindings[$event] ?? $event;
        $this->play($sound);
    }

    public function unload(): void
    {
        // nothing to release
    }

    public function __destruct()
    {
        $this->unload();
    }
}
