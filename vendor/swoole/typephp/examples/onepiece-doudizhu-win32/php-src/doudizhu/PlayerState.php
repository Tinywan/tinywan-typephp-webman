<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Games\OnePieceDoudizhu;

/**
 * 单名玩家运行时状态。
 */
final class PlayerState
{
    /** @var list<Card> */
    public array $hand;

    public ?string $characterId = null;
    public ?string $faction = null;
    public bool $isLandlord = false;

    /** 技能费用状态 */
    public array $skill = [
        'chargesLeft' => 0,
        'usedOnce' => false,
        'haki' => 0,
        'cooldown' => 0,
    ];

    /** 预置的下一手技能（onPlay/onBombPlayed） */
    public ?string $armed = null;
    public ?int $armedTarget = null;

    /**
     * @param list<Card> $hand
     */
    public function __construct(array $hand)
    {
        $this->hand = $hand;
    }

    public function initSkill(Skill $skill): void
    {
        if ($skill->cost === 'once') {
            $this->skill['usedOnce'] = false;
        } elseif (\str_starts_with($skill->cost, 'charges:')) {
            $this->skill['chargesLeft'] = (int) \substr($skill->cost, 8);
        } elseif (\str_starts_with($skill->cost, 'haki:')) {
            $this->skill['haki'] = (int) \substr($skill->cost, 5);
        }
    }
}
