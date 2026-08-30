<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Games\OnePieceDoudizhu;

/**
 * 从一手牌枚举所有合法牌型（用于 AI 决策与合法性校验）。
 * 同 (type,rank,length) 的去重，避免手牌多花色导致组合爆炸。
 */
final class MoveGenerator
{
    /**
     * @param list<Card> $hand
     * @return list<Combo>
     */
    public static function all(array $hand): array
    {
        if ($hand === []) {
            return [];
        }
        $byRank = [];
        foreach ($hand as $c) {
            $byRank[$c->rank][] = $c;
        }
        $ranks = \array_keys($byRank);
        \sort($ranks);
        $has = static fn (int $r) => isset($byRank[$r]);
        $cnt = static fn (int $r) => \count($byRank[$r] ?? []);

        $out = [];
        $seen = [];

        $push = static function (array $cards) use (&$out, &$seen): void {
            $combo = Combo::parse($cards);
            if ($combo === null) {
                return;
            }
            $key = $combo->type . ':' . $combo->rank . ':' . $combo->length;
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;
            $out[] = $combo;
        };

        // 单 / 对 / 三
        foreach ($ranks as $r) {
            $push([$byRank[$r][0]]);
            if ($cnt($r) >= 2) {
                $push([$byRank[$r][0], $byRank[$r][1]]);
            }
            if ($cnt($r) >= 3) {
                $push([$byRank[$r][0], $byRank[$r][1], $byRank[$r][2]]);
            }
        }

        // 三带一 / 三带二
        foreach ($ranks as $r) {
            if ($cnt($r) < 3) {
                continue;
            }
            $triple = [$byRank[$r][0], $byRank[$r][1], $byRank[$r][2]];
            foreach ($ranks as $s) {
                if ($s === $r) {
                    continue;
                }
                if ($cnt($s) >= 1) {
                    $push(\array_merge($triple, [$byRank[$s][0]]));
                }
                if ($cnt($s) >= 2) {
                    $push(\array_merge($triple, [$byRank[$s][0], $byRank[$s][1]]));
                }
            }
        }

        // 顺子 (5..12 连)
        for ($len = 5; $len <= 12; $len++) {
            for ($start = 3; $start + $len - 1 <= 14; $start++) {
                $ok = true;
                $cards = [];
                for ($k = 0; $k < $len; $k++) {
                    $rr = $start + $k;
                    if (!$has($rr)) {
                        $ok = false;
                        break;
                    }
                    $cards[] = $byRank[$rr][0];
                }
                if ($ok) {
                    $push($cards);
                }
            }
        }

        // 连对 (3..10 连)
        for ($len = 3; $len <= 10; $len++) {
            for ($start = 3; $start + $len - 1 <= 14; $start++) {
                $ok = true;
                $cards = [];
                for ($k = 0; $k < $len; $k++) {
                    $rr = $start + $k;
                    if ($cnt($rr) < 2) {
                        $ok = false;
                        break;
                    }
                    $cards[] = $byRank[$rr][0];
                    $cards[] = $byRank[$rr][1];
                }
                if ($ok) {
                    $push($cards);
                }
            }
        }

        // 飞机（纯三连，2..6 连）
        for ($len = 2; $len <= 6; $len++) {
            for ($start = 3; $start + $len - 1 <= 14; $start++) {
                $ok = true;
                $cards = [];
                for ($k = 0; $k < $len; $k++) {
                    $rr = $start + $k;
                    if ($cnt($rr) < 3) {
                        $ok = false;
                        break;
                    }
                    $cards[] = $byRank[$rr][0];
                    $cards[] = $byRank[$rr][1];
                    $cards[] = $byRank[$rr][2];
                }
                if ($ok) {
                    $push($cards);
                }
            }
        }

        // 炸弹
        foreach ($ranks as $r) {
            if ($cnt($r) === 4) {
                $push($byRank[$r]);
            }
        }

        // 火箭
        if ($has(Card::JOKER_SMALL) && $has(Card::JOKER_BIG)) {
            $push([$byRank[Card::JOKER_SMALL][0], $byRank[Card::JOKER_BIG][0]]);
        }

        return $out;
    }
}
