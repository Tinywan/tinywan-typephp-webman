<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Games\OnePieceDoudizhu;

/**
 * 54 张牌组：4 花色 × 3..2 + 双王。洗牌后发 3 手 + 3 张底牌。
 */
final class Deck
{
    /**
     * @return list<Card>
     */
    public static function build(): array
    {
        $cards = [];
        foreach (Card::SUITS as $suit) {
            for ($r = 3; $r <= 15; $r++) {
                $cards[] = new Card($r, $suit);
            }
        }
        $cards[] = Card::smallJoker();
        $cards[] = Card::bigJoker();

        return $cards;
    }

    /**
     * 洗牌发牌。
     *
     * @return array{hands: array<int, list<Card>>, bottom: list<Card>}
     */
    public static function deal(): array
    {
        $cards = self::build();
        \shuffle($cards);

        $hands = [[], [], []];
        for ($i = 0; $i < 51; $i++) {
            $hands[$i % 3][] = $cards[$i];
        }
        $bottom = \array_slice($cards, 51, 3);

        foreach ($hands as $i => $hand) {
            self::sort($hands[$i]);
        }

        return ['hands' => $hands, 'bottom' => $bottom];
    }

    /**
     * 按牌力升序排序（同点花色保持稳定）。
     *
     * @param list<Card> $hand
     */
    public static function sort(array &$hand): void
    {
        \usort($hand, static fn (Card $a, Card $b): int => $a->rank <=> $b->rank);
    }
}
