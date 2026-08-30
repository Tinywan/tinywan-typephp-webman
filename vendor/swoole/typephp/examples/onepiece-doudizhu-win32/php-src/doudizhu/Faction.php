<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Games\OnePieceDoudizhu;

/**
 * 三大势力：海军本部 / 王下七武海 / 四皇。
 * 每个势力有「特性」描述（影响 AI 行为与部分被动），成员使用各自主动技能。
 */
final class Faction
{
    public const NAVY = 'navy';        // 海军本部 — 绝对正义（控制/反制）
    public const WARLORD = 'warlord';  // 王下七武海 — 被招安的海盗（诡诈/交换）
    public const EMPEROR = 'emperor';  // 四皇 — 新世界霸主（压制/掠夺）

    /** @var array<string, array{name:string, creed:string, trait:string, color:string}> */
    public static array $defs = [
        self::NAVY => [
            'name' => '海军本部',
            'creed' => '绝对正义',
            'trait' => '正义铁拳：每局可反制一次对手炸弹；情报：叫完地主可查看底牌。',
            'color' => '#1e3a8a',
        ],
        self::WARLORD => [
            'name' => '王下七武海',
            'creed' => '被招安的海盗',
            'trait' => '协定漏洞：被迫过牌时可改出最小单张续命（2 次）；凭实力借：每局交换 1 张牌。',
            'color' => '#7c3aed',
        ],
        self::EMPEROR => [
            'name' => '四皇',
            'creed' => '新世界霸主',
            'trait' => '霸王色霸气：持有霸气 token，出牌时花费使其凌驾一切非炸；召集：每局抽 1 张。',
            'color' => '#b91c1c',
        ],
    ];

    public static function name(string $id): string
    {
        return self::$defs[$id]['name'] ?? $id;
    }

    public static function color(string $id): string
    {
        return self::$defs[$id]['color'] ?? '#444444';
    }
}
