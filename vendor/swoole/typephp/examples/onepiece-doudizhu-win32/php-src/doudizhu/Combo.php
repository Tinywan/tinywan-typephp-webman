<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Games\OnePieceDoudizhu;

use RuntimeException;

/**
 * 牌型识别与比较。
 *
 * 支持：单/对/三/三带一/三带二/顺子/连对/飞机(含翼)/炸弹/火箭。
 * 比较规则：火箭 > 炸弹(比点) > 普通(同型同长比点)。技能修正（霸气/不可拦截）
 * 由 Engine 在对局层处理，不在本类内。
 */
final class Combo
{
    public function __construct(
        public string $type,
        public int $rank,      // 主要比较点（炸弹/火箭用 17）
        public int $length,    // 顺子/连对/飞机的长度
        public array $cards,   // list<Card>
        public bool $isBomb = false,
        public bool $isRocket = false,
    ) {
    }

    /**
     * @param list<Card> $cards
     */
    public static function parse(array $cards): ?self
    {
        $n = \count($cards);
        if ($n === 0) {
            return null;
        }

        $byRank = [];
        foreach ($cards as $c) {
            $byRank[$c->rank][] = $c;
        }
        $ranks = \array_keys($byRank);
        \sort($ranks);
        $counts = [];
        foreach ($ranks as $r) {
            $counts[$r] = \count($byRank[$r]);
        }

        // 火箭（双王）
        if ($n === 2 && isset($byRank[Card::JOKER_SMALL]) && isset($byRank[Card::JOKER_BIG])) {
            return new self('rocket', Card::JOKER_BIG, 1, $cards, false, true);
        }

        // 炸弹（四同）
        if ($n === 4 && \count($ranks) === 1) {
            return new self('bomb', $ranks[0], 1, $cards, true);
        }

        // 单
        if ($n === 1) {
            return new self('single', $ranks[0], 1, $cards);
        }

        // 对
        if ($n === 2 && \count($ranks) === 1) {
            return new self('pair', $ranks[0], 1, $cards);
        }

        // 三
        if ($n === 3 && \count($ranks) === 1) {
            return new self('triple', $ranks[0], 1, $cards);
        }

        // 三带一
        if ($n === 4 && \count($ranks) === 2) {
            $triple = self::rankWithCount($counts, 3);
            if ($triple !== null) {
                return new self('triple1', $triple, 1, $cards);
            }

            return null;
        }

        // 三带二
        if ($n === 5 && \count($ranks) === 2) {
            $triple = self::rankWithCount($counts, 3);
            $pair = self::rankWithCount($counts, 2);
            if ($triple !== null && $pair !== null) {
                return new self('triple2', $triple, 1, $cards);
            }

            return null;
        }

        $maxRank = \max($ranks);
        $allOnes = \min($counts) === 1 && \max($counts) === 1;
        $allTwos = \min($counts) === 2 && \max($counts) === 2;
        $allThrees = \min($counts) === 3 && \max($counts) === 3;

        // 顺子（≥5 连续单张，不含 2/王）
        if ($allOnes && $n >= 5 && $maxRank <= 14 && self::isConsecutive($ranks)) {
            return new self('straight', $maxRank, $n, $cards);
        }

        // 连对（≥3 连续对子，不含 2/王）
        if ($allTwos && $n >= 6 && $maxRank <= 14 && self::isConsecutive($ranks)) {
            return new self('straight2', $maxRank, (int) ($n / 2), $cards);
        }

        // 飞机（≥2 连续三张，可带翼）
        if ($allThrees) {
            return new self('plane', $maxRank, $n, $cards);
        }
        $plane = self::planeWithWings($ranks, $counts, $n, $cards);
        if ($plane !== null) {
            return $plane;
        }

        return null;
    }

    /**
     * @param array<int,int> $counts
     */
    private static function rankWithCount(array $counts, int $want): ?int
    {
        foreach ($counts as $r => $c) {
            if ($c === $want) {
                return $r;
            }
        }

        return null;
    }

    /**
     * @param list<int>    $ranks
     * @param array<int,int> $counts
     * @param list<Card>   $cards
     */
    private static function planeWithWings(array $ranks, array $counts, int $n, array $cards): ?self
    {
        $tripleRanks = [];
        $wingRanks = [];
        foreach ($ranks as $r) {
            if ($counts[$r] === 3) {
                $tripleRanks[] = $r;
            } else {
                $wingRanks[] = $r;
            }
        }
        if (\count($tripleRanks) < 2 || !self::isConsecutive($tripleRanks) || \max($tripleRanks) > 14) {
            return null;
        }
        $t = \count($tripleRanks);
        $expectedWings = $n - 3 * $t;

        // 无翼
        if ($expectedWings === 0) {
            return new self('plane', \max($tripleRanks), $t, $cards);
        }
        // 单翼：t 张单牌
        if ($expectedWings === $t && \count($wingRanks) === $t && self::allCount($wingRanks, $counts, 1)) {
            return new self('plane1', \max($tripleRanks), $t, $cards);
        }
        // 对翼：t 个对子
        if ($expectedWings === 2 * $t && \count($wingRanks) === $t && self::allCount($wingRanks, $counts, 2)) {
            return new self('plane2', \max($tripleRanks), $t, $cards);
        }

        return null;
    }

    /**
     * @param list<int>    $ranks
     * @param array<int,int> $counts
     */
    private static function allCount(array $ranks, array $counts, int $want): bool
    {
        foreach ($ranks as $r) {
            if (($counts[$r] ?? 0) !== $want) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<int> $ranks
     */
    private static function isConsecutive(array $ranks): bool
    {
        $r = \array_values($ranks);
        if (\count($r) < 2) {
            return false;
        }
        \sort($r);
        for ($i = 1; $i < \count($r); $i++) {
            if ($r[$i] !== $r[$i - 1] + 1) {
                return false;
            }
        }

        return true;
    }

    /** a 是否能压过当前桌面 lastPlay（b）。b=null 表示自由出牌。 */
    public static function beats(self $a, ?self $b): bool
    {
        if ($b === null) {
            return true;
        }
        if ($a->isRocket) {
            return true;
        }
        if ($b->isRocket) {
            return false;
        }
        if ($a->isBomb) {
            if ($b->isBomb) {
                return $a->rank > $b->rank;
            }

            return true;
        }
        if ($b->isBomb) {
            return false;
        }
        if ($a->type !== $b->type) {
            return false;
        }
        if ($a->length !== $b->length) {
            return false;
        }

        return $a->rank > $b->rank;
    }

    public function describe(): string
    {
        $names = [
            'single' => '单张', 'pair' => '对子', 'triple' => '三张',
            'triple1' => '三带一', 'triple2' => '三带二', 'straight' => '顺子',
            'straight2' => '连对', 'plane' => '飞机', 'plane1' => '飞机带单',
            'plane2' => '飞机带对', 'bomb' => '炸弹', 'rocket' => '王炸',
        ];
        $label = $names[$this->type] ?? $this->type;

        return \sprintf('%s(%s)', $label, \implode('', \array_map(static fn (Card $c) => $c->label(), $this->cards)));
    }
}
