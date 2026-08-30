<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Games\OnePieceDoudizhu;

/**
 * AI 对手：手牌评估 + 跟牌/首出启发式 + 势力感知行为 + 技能效用决策。
 *
 * 设计为「无状态决策器」：每次轮到 AI 时调用 act() 完成整个回合动作。
 * 赤犬反击（counterBomb）属于反应式，由驱动器在对手出炸弹后单独调用 maybeCounter()。
 */
final class Ai
{
    /** 叫分：按手牌强度评估 0..3。 */
    public static function bid(array $hand): int
    {
        $score = 0;
        $byRank = [];
        foreach ($hand as $c) {
            $byRank[$c->rank] = ($byRank[$c->rank] ?? 0) + 1;
        }
        foreach ($byRank as $r => $n) {
            if ($n === 4) {
                $score += 7; // 炸弹
            }
            if ($r === Card::JOKER_SMALL) {
                $score += 4;
            }
            if ($r === Card::JOKER_BIG) {
                $score += 5;
            }
            if ($r === 15) {
                $score += 2; // 2
            }
            if ($r === 14 || $r === 13) {
                $score += 1; // A/K
            }
        }
        if ($score >= 16) {
            return 3;
        }
        if ($score >= 10) {
            return 2;
        }
        if ($score >= 6) {
            return 1;
        }

        return 0;
    }

    /** 执行 AI 玩家的整个回合（含技能）。 */
    public static function act(Game $g, int $player): void
    {
        if ($g->phase !== 'playing' || $g->turn !== $player) {
            return;
        }
        self::maybeTurnStartSkill($g, $player);
        if ($g->phase !== 'playing' || $g->turn !== $player) {
            return;
        }

        $moves = $g->legalMoves($player);
        if ($g->lastPlay === null) {
            if ($moves === []) {
                return;
            }
            $mv = self::pickLead($g, $player, $moves);
            self::maybeArmOnPlay($g, $player, $mv);
            self::maybeArmOnBomb($g, $player, $mv);
            $g->play($player, $mv->cards);

            return;
        }

        $beat = self::pickFollow($g, $player, $moves);
        if ($beat === null) {
            $g->pass($player);

            return;
        }
        self::maybeArmOnPlay($g, $player, $beat);
        self::maybeArmOnBomb($g, $player, $beat);
        $g->play($player, $beat->cards);
    }

    /** 赤犬反击：对手刚出炸弹时调用，决定是否反击。 */
    public static function maybeCounter(Game $g, int $player): bool
    {
        if ($g->lastPlay === null || !$g->lastPlay['combo']->isBomb) {
            return false;
        }
        if ($g->players[$player]->characterId !== 'akainu') {
            return false;
        }
        $skill = Character::byId('akainu')->skill();
        if (!$g->canUse($player, $skill)) {
            return false;
        }
        $bomber = $g->lastPlay['player'];
        // 农民反击地主，或地主反击农民（阻止其快出完）
        $threat = $g->handCount($bomber) <= 5;
        if ($g->side($player) !== $g->side($bomber) || $threat) {
            $g->counterBomb($player);

            return true;
        }

        return false;
    }

    // ---------------------------------------------------------------- 决策细节
    /**
     * 首出时挑选要甩出的牌组（公开，供控制器提示与测试复用）。
     *
     * @param list<Combo> $moves
     */
    public static function pickLead(Game $g, int $player, array $moves): Combo
    {
        $nonBomb = \array_filter($moves, static fn (Combo $c) => !$c->isBomb && !$c->isRocket);
        if ($nonBomb !== []) {
            // 优先甩出牌数多的组合（顺/飞机/连对），其次最小单
            \usort($nonBomb, static function (Combo $a, Combo $b): int {
                $ca = \count($a->cards);
                $cb = \count($b->cards);
                if ($cb !== $ca) {
                    return $cb <=> $ca;
                }

                return $a->rank <=> $b->rank;
            });

            return $nonBomb[0];
        }
        // 只剩炸弹/火箭
        \usort($moves, static fn (Combo $a, Combo $b): int => $a->rank <=> $b->rank);

        return $moves[0];
    }

    /**
     * 跟牌时挑选要压过的牌组（公开，供控制器提示与测试复用）。
     *
     * @param list<Combo> $moves
     */
    public static function pickFollow(Game $g, int $player, array $moves): ?Combo
    {
        if ($moves === []) {
            return null;
        }
        $oppHand = PHP_INT_MAX;
        foreach ([0, 1, 2] as $p) {
            if ($p !== $player && $g->side($p) !== $g->side($player)) {
                $oppHand = \min($oppHand, $g->handCount($p));
            }
        }
        $nonBomb = \array_filter($moves, static fn (Combo $c) => !$c->isBomb && !$c->isRocket);
        if ($nonBomb !== []) {
            \usort($nonBomb, static fn (Combo $a, Combo $b): int => $a->rank <=> $b->rank);

            return $nonBomb[0];
        }
        // 只有炸弹/火箭：对手快出完才用
        if ($oppHand <= 2) {
            \usort($moves, static fn (Combo $a, Combo $b): int => $a->rank <=> $b->rank);

            return $moves[0];
        }

        return null; // 留炸弹
    }

    private static function maybeTurnStartSkill(Game $g, int $player): void
    {
        $ps = $g->players[$player];
        if ($ps->characterId === null) {
            return;
        }
        $skill = Character::byId($ps->characterId)->skill();
        if ($skill->trigger !== 'onTurnStart') {
            return;
        }
        if (!$g->canUse($player, $skill)) {
            return;
        }
        // 目标：手牌最少的对手（最具威胁）
        $target = null;
        $best = PHP_INT_MAX;
        foreach ([0, 1, 2] as $p) {
            if ($p === $player) {
                continue;
            }
            $c = $g->handCount($p);
            if ($c < $best) {
                $best = $c;
                $target = $p;
            }
        }
        if ($target === null) {
            return;
        }
        // 势力感知：海军/七武海更爱用控制技；四皇（大妈）偷牌也积极
        $g->armSkill($player, $target);
    }

    private static function maybeArmOnPlay(Game $g, int $player, Combo $mv): void
    {
        $ps = $g->players[$player];
        if ($ps->characterId === null || $ps->armed !== null) {
            return;
        }
        $skill = Character::byId($ps->characterId)->skill();
        if ($skill->trigger !== 'onPlay') {
            return;
        }
        if (!$g->canUse($player, $skill)) {
            return;
        }

        $faction = $ps->faction;
        if ($skill->id === 'garp_shock' || $skill->id === 'mihawk_unblock') {
            if ($mv->type === 'single' || $mv->type === 'pair') {
                // 海军/七武海：用控制技压制
                $g->armSkill($player);
            }
        } elseif ($skill->id === 'shanks_haki') {
            // 四皇：手牌少时花霸气锁 trick；否则偶尔用
            if ($g->handCount($player) <= 6 || ($g->lastPlay !== null && \rand(0, 2) === 0)) {
                $g->armSkill($player);
            }
        }
    }

    private static function maybeArmOnBomb(Game $g, int $player, Combo $mv): void
    {
        $ps = $g->players[$player];
        if ($ps->characterId === null || $ps->armed !== null) {
            return;
        }
        $skill = Character::byId($ps->characterId)->skill();
        if ($skill->trigger !== 'onBombPlayed' || !$mv->isBomb) {
            return;
        }
        if (!$g->canUse($player, $skill)) {
            return;
        }
        if ($skill->id === 'whitebeard_quake') {
            $g->armSkill($player);
        }
    }
}
