<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Games\OnePieceDoudizhu;

use InvalidArgumentException;
use RuntimeException;

/**
 * 对局状态 + 规则引擎 + 技能调度。
 *
 * 阶段：bidding（叫地主） → playing（出牌） → over。
 * 技能采用「先 arm（激活）后行动」模型：
 *   - onTurnStart 技能：arm 时立即生效（冻结/石化/偷牌/推牌）
 *   - onPlay / onBombPlayed 技能：arm 后给「下一手牌」附加修正（跳过/不可拦截/霸气/炸弹+1）
 *   - 赤犬反击：对手出炸弹后调用 counterBomb() 取消
 */
final class Game
{
    /** @var list<PlayerState> */
    public array $players;
    public ?int $landlord = null;
    public int $turn = 0;

    /** @var array{player:int, combo:Combo, hakiActive:bool, unblockable:bool, bombBonus:int}|null */
    public ?array $lastPlay = null;
    public int $passes = 0;
    public string $phase = 'bidding';
    public ?int $winner = null;
    public ?string $winnerSide = null;
    /** @var list<string> */
    public array $log = [];

    /** 战场修正（按目标玩家） */
    public array $mods = [
        'skipNext' => [false, false, false],
        'frozen' => [0, 0, 0],
        'petrified' => [0, 0, 0],
        'bombDisabled' => [0, 0, 0],
    ];

    /** 叫分状态 */
    public array $bidding = [
        'scores' => [0, 0, 0],
        'current' => 0,
        'highest' => 0,
        'highestPlayer' => null,
        'acted' => [false, false, false],
    ];

    /** @var list<Card> 底牌（叫完分给地主） */
    public array $bottom = [];
    /** 是否已把底牌发给地主（用于「情报」查看） */
    public bool $bottomRevealed = false;

    /** 事件回调（音效/UI 钩子）：fn(event, payload) */
    public $onEvent = null;

    /**
     * @param list<PlayerState> $players
     * @param list<Card>        $bottom
     */
    public function __construct(array $players, array $bottom)
    {
        $this->players = $players;
        $this->bottom = $bottom;
        $this->turn = 0;
    }

    public function side(int $player): string
    {
        return $player === $this->landlord ? 'landlord' : 'peasant';
    }

    public function isOver(): bool
    {
        return $this->phase === 'over';
    }

    public function handCount(int $player): int
    {
        return \count($this->players[$player]->hand);
    }

    // ---------------------------------------------------------------- 叫地主
    /**
     * @return 'ok'|'redeal'|'started'
     */
    public function bid(int $player, int $score): string
    {
        if ($this->phase !== 'bidding') {
            throw new RuntimeException('not in bidding phase');
        }
        if ($this->bidding['acted'][$player]) {
            throw new InvalidArgumentException('player already bid');
        }
        $score = \max(0, \min(3, $score));
        $this->bidding['scores'][$player] = $score;
        $this->bidding['acted'][$player] = true;
        if ($score > $this->bidding['highest']) {
            $this->bidding['highest'] = $score;
            $this->bidding['highestPlayer'] = $player;
        }
        $this->emit('bid', ['player' => $player, 'score' => $score]);

        $actedCount = \count(\array_filter($this->bidding['acted']));
        if ($actedCount < 3 && $score < 3) {
            // 轮到下一位
            $this->bidding['current'] = ($player + 1) % 3;

            return 'ok';
        }

        // 结束叫分
        if ($this->bidding['highest'] === 0) {
            return 'redeal';
        }
        $this->startPlaying();

        return 'started';
    }

    private function startPlaying(): void
    {
        $this->landlord = $this->bidding['highestPlayer'];
        $this->players[$this->landlord]->isLandlord = true;
        // 给地主发底牌
        foreach ($this->bottom as $c) {
            $this->players[$this->landlord]->hand[] = $c;
        }
        Deck::sort($this->players[$this->landlord]->hand);
        $this->bottomRevealed = true;
        $this->turn = $this->landlord;
        $this->phase = 'playing';
        $this->emit('gameStart', ['landlord' => $this->landlord]);
    }

    // ---------------------------------------------------------------- 出牌
    /**
     * @param list<Card> $cards
     * @param int|null   $skillTarget 部分技能需要指定目标
     */
    public function play(int $player, array $cards, ?int $skillTarget = null): void
    {
        $this->ensureTurn($player);
        if ($this->mods['frozen'][$player] > 0) {
            throw new RuntimeException('玩家被冻结，本回合无法出牌');
        }
        $combo = Combo::parse($cards);
        if ($combo === null) {
            throw new InvalidArgumentException('无效的牌型');
        }
        $this->assertCardsInHand($player, $cards);

        // 比较：考虑上一手的技能修正
        if ($this->lastPlay !== null) {
            if (!$this->beatsWithMods($combo, $this->lastPlay)) {
                throw new InvalidArgumentException('压不过上一手');
            }
        }

        // 白胡子：自身炸弹 +1（arm 标记 bombBonus）
        $bombBonus = 0;
        $hakiActive = false;
        $unblockable = false;
        $ps = $this->players[$player];
        if ($ps->armed !== null) {
            $skill = Character::byId($ps->characterId)->skill();
            if ($skill->trigger === 'onPlay' && $this->comboQualifies($combo, $skill)) {
                if ($skill->id === 'shanks_haki') {
                    $hakiActive = true;
                }
                if ($skill->id === 'mihawk_unblock') {
                    $unblockable = true;
                }
                if ($skill->id === 'garp_shock') {
                    // 下家跳过：在 advanceTurn 时处理
                    $this->mods['skipNext'][($player + 1) % 3] = true;
                }
            }
            if ($skill->trigger === 'onBombPlayed' && $combo->isBomb) {
                if ($skill->id === 'whitebeard_quake') {
                    $bombBonus = 1;
                }
            }
            // 技能真正生效时才扣费（once/charges/haki），防止无限使用
            $applied = ($skill->trigger === 'onPlay' && $this->comboQualifies($combo, $skill))
                || ($skill->trigger === 'onBombPlayed' && $combo->isBomb);
            if ($applied) {
                $this->consume($player, $skill);
            }
            $ps->armed = null;
        }

        // 移除手牌
        $this->removeCards($player, $cards);
        $this->lastPlay = [
            'player' => $player,
            'combo' => $combo,
            'hakiActive' => $hakiActive,
            'unblockable' => $unblockable,
            'bombBonus' => $bombBonus,
        ];
        $this->passes = 0;
        $this->emit('play', [
            'player' => $player,
            'combo' => $combo,
            'hakiActive' => $hakiActive,
            'unblockable' => $unblockable,
            'bombBonus' => $bombBonus,
        ]);

        // 赤犬反击窗口：若有人 armed 赤犬，自动在 counterBomb 中处理（AI/UI 调用）
        // 此处不自动触发

        if ($this->handCount($player) === 0) {
            $this->finish($player);

            return;
        }

        $this->advanceTurn();
    }

    public function pass(int $player): void
    {
        $this->ensureTurn($player);
        if ($this->lastPlay === null) {
            throw new InvalidArgumentException('首出不能过');
        }
        // 七武海「续命」：技能可改出最小单张（由 armSkill 处理，这里直接过）
        $this->passes++;
        $this->emit('pass', ['player' => $player]);
        $this->advanceTurn();
    }

    /** 赤犬反击：取消对手刚出的炸弹。 */
    public function counterBomb(int $player): void
    {
        if ($this->lastPlay === null || !$this->lastPlay['combo']->isBomb) {
            throw new RuntimeException('当前没有可反击的炸弹');
        }
        $ps = $this->players[$player];
        if ($ps->characterId === null) {
            throw new RuntimeException('无角色');
        }
        $skill = Character::byId($ps->characterId)->skill();
        if ($skill->id !== 'akainu_magma') {
            throw new RuntimeException('该角色无反击炸弹技能');
        }
        if (!$this->canUse($player, $skill)) {
            throw new RuntimeException('技能不可用');
        }
        $bomber = $this->lastPlay['player'];
        // 把炸弹牌还给出牌者
        foreach ($this->lastPlay['combo']->cards as $c) {
            $this->players[$bomber]->hand[] = $c;
        }
        Deck::sort($this->players[$bomber]->hand);
        // 该 trick 作废，反击者获得 lead 权
        $this->lastPlay = null;
        $this->passes = 0;
        $this->consume($player, $skill);
        $this->mods['bombDisabled'][$bomber] = 2; // 其下个炸弹禁用
        $this->turn = $player;
        $this->emit('counter', ['player' => $player, 'target' => $bomber]);
    }

    // ---------------------------------------------------------------- 技能
    public function canUse(int $player, Skill $skill): bool
    {
        if ($this->phase !== 'playing') {
            return false;
        }
        if ($this->mods['petrified'][$player] > 0) {
            return false; // 被石化
        }
        $ps = $this->players[$player];
        if ($skill->cost === 'once' && $ps->skill['usedOnce']) {
            return false;
        }
        if (\str_starts_with($skill->cost, 'charges:')) {
            $n = (int) \substr($skill->cost, 8);

            return $ps->skill['chargesLeft'] >= $n;
        }
        if (\str_starts_with($skill->cost, 'haki:')) {
            $n = (int) \substr($skill->cost, 5);

            return $ps->skill['haki'] >= $n;
        }

        return true;
    }

    /**
     * 激活技能。onTurnStart 立即生效；onPlay/onBombPlayed 预置到下一手。
     */
    public function armSkill(int $player, ?int $target = null): void
    {
        if ($this->phase !== 'playing') {
            throw new RuntimeException('非出牌阶段');
        }
        $ps = $this->players[$player];
        if ($ps->characterId === null) {
            throw new RuntimeException('玩家未分配角色');
        }
        $skill = Character::byId($ps->characterId)->skill();
        if (!$this->canUse($player, $skill)) {
            throw new RuntimeException('技能不可用（费用不足或被石化）');
        }

        if ($skill->trigger === 'onTurnStart') {
            Skill::apply($this, $player, $target);
            $this->consume($player, $skill);
            $this->emit('skill', ['player' => $player, 'skill' => $skill->id, 'target' => $target]);

            return;
        }

        // onPlay / onBombPlayed：预置
        if ($ps->armed !== null) {
            throw new RuntimeException('已有预置技能');
        }
        $ps->armed = $skill->id;
        $ps->armedTarget = $target;
        $this->emit('arm', ['player' => $player, 'skill' => $skill->id]);
    }

    private function consume(int $player, Skill $skill): void
    {
        $ps = $this->players[$player];
        if ($skill->cost === 'once') {
            $ps->skill['usedOnce'] = true;
        } elseif (\str_starts_with($skill->cost, 'charges:')) {
            $n = (int) \substr($skill->cost, 8);
            $ps->skill['chargesLeft'] -= $n;
        } elseif (\str_starts_with($skill->cost, 'haki:')) {
            $n = (int) \substr($skill->cost, 5);
            $ps->skill['haki'] -= $n;
        }
    }

    private function comboQualifies(Combo $combo, Skill $skill): bool
    {
        if ($skill->id === 'garp_shock' || $skill->id === 'mihawk_unblock') {
            return $combo->type === 'single' || $combo->type === 'pair';
        }
        if ($skill->id === 'shanks_haki') {
            return true; // 任意手牌可附霸气
        }

        return true;
    }

    // ---------------------------------------------------------------- 流转
    private function ensureTurn(int $player): void
    {
        if ($this->phase !== 'playing') {
            throw new RuntimeException('非出牌阶段');
        }
        if ($player !== $this->turn) {
            throw new InvalidArgumentException('还没轮到该玩家');
        }
    }

    private function advanceTurn(): void
    {
        // trick 结束：连续两人 pass
        if ($this->passes >= 2) {
            $this->lastPlay = null;
            $this->passes = 0;
            // lead 权归上一个出牌者（this->turn 已是最后出牌者，因 pass 不改变 lastPlay 持有者）
        }
        $next = ($this->turn + 1) % 3;
        // 跳过被冻结/被跳过的玩家
        $guard = 0;
        while ($guard < 3) {
            if ($this->mods['skipNext'][$next]) {
                $this->mods['skipNext'][$next] = false;
                $this->emit('skip', ['player' => $next]);
                $next = ($next + 1) % 3;
                $guard++;

                continue;
            }
            if ($this->mods['frozen'][$next] > 0) {
                $this->mods['frozen'][$next]--;
                $this->emit('frozenSkip', ['player' => $next]);
                $next = ($next + 1) % 3;
                $guard++;

                continue;
            }
            break;
        }
        // 递减石化/炸弹禁用计数（在对应玩家回合开始时）
        if ($this->mods['petrified'][$next] > 0) {
            $this->mods['petrified'][$next]--;
        }
        if ($this->mods['bombDisabled'][$next] > 0) {
            $this->mods['bombDisabled'][$next]--;
        }
        $this->turn = $next;
    }

    private function finish(int $player): void
    {
        $this->winner = $player;
        $this->winnerSide = $this->side($player);
        $this->phase = 'over';
        $this->emit('gameOver', ['winner' => $player, 'side' => $this->winnerSide]);
    }

    // ---------------------------------------------------------------- 比较（含技能修正）
    /**
     * @param array{combo:Combo,hakiActive:bool,unblockable:bool,bombBonus:int} $last
     */
    private function beatsWithMods(Combo $a, array $last): bool
    {
        $b = $last['combo'];
        $bombBonus = $last['bombBonus'];
        // 复制 b 并叠加炸弹加成用于比较
        if ($bombBonus > 0 && $b->isBomb) {
            $b = new Combo($b->type, $b->rank + $bombBonus, $b->length, $b->cards, true);
        }

        // 霸气/不可拦截：非炸不可压
        if (($last['hakiActive'] || $last['unblockable']) && !$a->isBomb && !$a->isRocket) {
            return false;
        }

        return Combo::beats($a, $b);
    }

    // ---------------------------------------------------------------- 手牌校验
    /**
     * @param list<Card> $cards
     */
    private function assertCardsInHand(int $player, array $cards): void
    {
        $handIds = [];
        foreach ($this->players[$player]->hand as $c) {
            $handIds[$c->id()] = true;
        }
        foreach ($cards as $c) {
            if (!isset($handIds[$c->id()])) {
                throw new InvalidArgumentException('手牌中没有该牌: ' . $c->label());
            }
            $handIds[$c->id()] = false; // 防止同 id 重复（同点不同花色安全）
        }
    }

    /**
     * @param list<Card> $cards
     */
    private function removeCards(int $player, array $cards): void
    {
        $remove = [];
        foreach ($cards as $c) {
            $remove[$c->id()] = ($remove[$c->id()] ?? 0) + 1;
        }
        $kept = [];
        foreach ($this->players[$player]->hand as $c) {
            if (($remove[$c->id()] ?? 0) > 0) {
                $remove[$c->id()]--;
            } else {
                $kept[] = $c;
            }
        }
        $this->players[$player]->hand = $kept;
    }

    // ---------------------------------------------------------------- 合法着法（AI/提示）
    /**
     * @return list<Combo>
     */
    public function legalMoves(int $player): array
    {
        $hand = $this->players[$player]->hand;
        $all = MoveGenerator::all($hand);
        if ($this->lastPlay === null) {
            return $all;
        }

        return \array_values(\array_filter($all, fn (Combo $c) => $this->beatsWithMods($c, $this->lastPlay)));
    }

    // ---------------------------------------------------------------- 事件
    /**
     * @param mixed $payload
     */
    private function emit(string $event, $payload): void
    {
        if (\is_callable($this->onEvent)) {
            ($this->onEvent)($event, $payload);
        }
        $this->log[] = \sprintf('[%s] %s', $event, \is_array($payload) ? \json_encode($payload) : (string) $payload);
    }
}
