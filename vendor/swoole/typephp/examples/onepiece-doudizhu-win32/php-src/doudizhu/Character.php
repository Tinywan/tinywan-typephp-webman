<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Games\OnePieceDoudizhu;

/**
 * 9 名代表性角色（每势力 3 名），各带一个独特技能。
 * 技能深度结合卡牌对战：onPlay/onBombPlayed 挂出牌修正，onTurnStart 直接施加战场状态。
 */
final class Character
{
    public function __construct(
        public string $id,
        public string $name,
        public string $title,
        public string $faction,
        public string $skillId,
        public string $skillName,
        public string $skillDesc,
        public string $skillTrigger,
        public string $skillCost,
    ) {
    }

    public function skill(): Skill
    {
        return new Skill(
            $this->skillId,
            $this->skillName,
            $this->skillDesc,
            $this->skillTrigger,
            $this->skillCost,
            $this->faction,
        );
    }

    /** @return list<self> */
    public static function all(): array
    {
        return [
            // 海军本部 — 控制 / 反制
            new self('garp', '蒙奇·D·卡普', '拳骨', Faction::NAVY, 'garp_shock', '银河碎拳',
                '出单/对时启用：该手牌附带冲击波，下家本 trick 强制 pass。', 'onPlay', 'once'),
            new self('akainu', '萨卡斯基', '冥狗', Faction::NAVY, 'akainu_magma', '岩浆灼烧',
                '对手出炸弹时反击：使其炸弹无效，并禁用其下个炸弹。', 'onBombPlayed', 'charges:2'),
            new self('aokiji', '库赞', '冰河时代', Faction::NAVY, 'aokiji_freeze', '冻结',
                '冻结一名对手 1 回合（自动 pass）。', 'onTurnStart', 'once'),

            // 王下七武海 — 诡诈 / 交换
            new self('mihawk', '鹰眼 米霍克', '世界第一大剑豪', Faction::WARLORD, 'mihawk_unblock', '黑刀·夜',
                '出单/对时启用：该手牌不可被非炸拦截。', 'onPlay', 'charges:2'),
            new self('boa', '波雅·汉库克', '女帝', Faction::WARLORD, 'boa_petrify', '虏之矢',
                '石化一名对手 2 回合（禁用其技能）。', 'onTurnStart', 'once'),
            new self('kuma', '巴索罗米·熊', '暴君', Faction::WARLORD, 'kuma_push', '肉球推送',
                '将你最小的一张手牌推给指定对手（扰乱牌型）。', 'onTurnStart', 'once'),

            // 四皇 — 压制 / 掠夺
            new self('shanks', '香克斯', '红发', Faction::EMPEROR, 'shanks_haki', '霸王色霸气',
                '出牌时花费 1 霸气：该手牌凌驾一切非炸（除非炸弹/火箭）。', 'onPlay', 'haki:1'),
            new self('whitebeard', '爱德华·纽盖特', '白胡子', Faction::EMPEROR, 'whitebeard_quake', '震震果实',
                '出炸弹时启用：炸弹威力 +1（视为更大炸弹）。', 'onBombPlayed', 'charges:2'),
            new self('bigmom', '夏洛特·玲玲', '大妈', Faction::EMPEROR, 'bigmom_steal', '魂魂召唤',
                '随机偷取一名对手 1 张手牌。', 'onTurnStart', 'once'),
        ];
    }

    public static function byId(string $id): self
    {
        static $map = null;
        if ($map === null) {
            $map = [];
            foreach (self::all() as $c) {
                $map[$c->id] = $c;
            }
        }

        if (!isset($map[$id])) {
            throw new \InvalidArgumentException("未知角色: $id");
        }

        return $map[$id];
    }

    /** @return list<self> */
    public static function byFaction(string $faction): array
    {
        return \array_values(\array_filter(self::all(), static fn (self $c) => $c->faction === $faction));
    }
}
