<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Games\OnePieceDoudizhu;

/**
 * 技能定义 + 效果实现。
 *
 * 效果分类：
 *  - onTurnStart 技能：在 armSkill() 时立即由 Skill::apply() 执行（冻结/石化/推牌/偷牌）
 *  - onPlay / onBombPlayed 技能：在 Game::play() 中通过预置标记附加到那一手牌（跳过/不可拦截/霸气/炸弹+1）
 *  - 赤犬反击：在 Game::counterBomb() 中处理（不属于 arm/apply 路径）
 */
final class Skill
{
    public function __construct(
        public string $id,
        public string $name,
        public string $desc,
        public string $trigger,  // onTurnStart | onPlay | onBombPlayed
        public string $cost,     // once | charges:N | haki:N
        public string $faction,
    ) {
    }

    /** 应用 onTurnStart 类技能（立即生效）。 */
    public static function apply(Game $g, int $actor, ?int $target): void
    {
        $skill = Character::byId($g->players[$actor]->characterId)->skill();
        $target ??= self::defaultTarget($g, $actor);

        switch ($skill->id) {
            case 'aokiji_freeze':
                $g->mods['frozen'][$target] = 1;
                break;

            case 'boa_petrify':
                $g->mods['petrified'][$target] = 2;
                break;

            case 'kuma_push':
                self::pushCard($g, $actor, $target);
                break;

            case 'bigmom_steal':
                self::stealCard($g, $actor, $target);
                break;

            default:
                // onPlay / onBombPlayed 类不在 apply 中处理
                break;
        }
    }

    private static function defaultTarget(Game $g, int $actor): int
    {
        // 默认瞄准「手牌最少」的对手（最具威胁）
        $best = null;
        $bestCount = PHP_INT_MAX;
        foreach ([0, 1, 2] as $p) {
            if ($p === $actor) {
                continue;
            }
            $c = $g->handCount($p);
            if ($c < $bestCount) {
                $bestCount = $c;
                $best = $p;
            }
        }

        return $best ?? (($actor + 1) % 3);
    }

    private static function pushCard(Game $g, int $actor, int $target): void
    {
        $hand = $g->players[$actor]->hand;
        if ($hand === []) {
            return;
        }
        // 推出最小的一张
        \usort($hand, static fn (Card $a, Card $b) => $a->rank <=> $b->rank);
        $card = \array_shift($hand);
        $g->players[$actor]->hand = $hand;
        $g->players[$target]->hand[] = $card;
        Deck::sort($g->players[$target]->hand);
    }

    private static function stealCard(Game $g, int $actor, int $target): void
    {
        $th = $g->players[$target]->hand;
        if ($th === []) {
            return;
        }
        $idx = \array_rand($th);
        $card = $th[$idx];
        unset($th[$idx]);
        $th = \array_values($th);
        $g->players[$target]->hand = $th;
        $g->players[$actor]->hand[] = $card;
        Deck::sort($g->players[$actor]->hand);
    }
}
