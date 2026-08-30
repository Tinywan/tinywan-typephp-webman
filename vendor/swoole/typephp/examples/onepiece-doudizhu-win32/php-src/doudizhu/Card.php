<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Games\OnePieceDoudizhu;

/**
 * 一张牌：rank 决定牌力，suit 仅用于显示与花色技能（四皇「领土宣言」）。
 *
 * 牌力序（升序）：
 *   3..10 -> 3..10
 *   J=11 Q=12 K=13 A=14 2=15
 *   小王=16 大王=17
 */
final class Card
{
    public const SUITS = ['♠', '♥', '♦', '♣']; // ♠ ♥ ♦ ♣
    public const JOKER_SMALL = 16;
    public const JOKER_BIG = 17;

    private const RANK_LABELS = [
        3 => '3', 4 => '4', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 9 => '9', 10 => '10',
        11 => 'J', 12 => 'Q', 13 => 'K', 14 => 'A', 15 => '2',
        16 => '小王', 17 => '大王',
    ];

    public function __construct(
        public int $rank,
        public string $suit = '',
    ) {
    }

    public static function smallJoker(): self
    {
        return new self(self::JOKER_SMALL, 'JOKER');
    }

    public static function bigJoker(): self
    {
        return new self(self::JOKER_BIG, 'JOKER');
    }

    /** 人类可读标签，如「♠A」「大王」。 */
    public function label(): string
    {
        if ($this->rank >= self::JOKER_SMALL) {
            return self::RANK_LABELS[$this->rank];
        }

        return $this->suit . self::RANK_LABELS[$this->rank];
    }

    /** 稳定 id，用于去重/比较（同点不同花色算不同牌）。 */
    public function id(): string
    {
        return $this->suit . ':' . $this->rank;
    }

    public function isJoker(): bool
    {
        return $this->rank >= self::JOKER_SMALL;
    }

    public function jsonSerialize(): array
    {
        return ['rank' => $this->rank, 'suit' => $this->suit, 'label' => $this->label()];
    }
}
