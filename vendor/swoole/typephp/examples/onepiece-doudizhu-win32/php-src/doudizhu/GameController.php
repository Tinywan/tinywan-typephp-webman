<?php

declare(strict_types=1);

namespace Yangweijie\Ui2\Games\OnePieceDoudizhu;

// NOTE: This is the Win32 / TypePHP port of the original libui-based
// GameController. libui's DrawContext / Brush / Color / FontDescriptor /
// StrokeParams / DrawTextAlign / TextWeight are re-declared as compatible
// shims in Render.php (same namespace), so all drawing calls below are
// unchanged. Windowing, input and timers are handled by the Win32 message
// loop in main.php plus the timer methods in this class.

/* ----------------------------- helpers ------------------------------ */

/**
 * 根据操作系统返回合适的系统字体（跨平台：macOS / Windows / Linux）。
 *
 * - macOS：`.AppleSystemUIFont` 系统 UI 字体，含中文与花色符号
 * - Windows：`Microsoft YaHei`（微软雅黑），含中文与 ♥♦♣♠ 花色符号
 * - Linux：`Noto Sans CJK SC`（优先），缺失时退回 Pango 通用 `Sans`
 *
 * 可通过环境变量 UI_FONT 强制指定字体族，便于在无头/特殊环境覆盖。
 */
function uiFontFamily(): string
{
    $env = \getenv('UI_FONT');
    if (\is_string($env) && $env !== '') {
        return $env;
    }
    $os = \strtoupper(\substr(PHP_OS, 0, 3));
    if ($os === 'WIN') {
        return 'Microsoft YaHei';
    }
    if ($os === 'LIN') {
        // 优先 Noto CJK；若未安装，Pango 会回退到系统默认无衬线（通常含 CJK）
        return 'Noto Sans CJK SC';
    }

    return '.AppleSystemUIFont';
}

/**
 * 全局字体常量（运行时按平台解析，避免重复定义）。
 * 必须以函数形式提供：TypePHP 编译器不允许文件顶层出现游离执行代码。
 */
function ensureDdzFont(): void
{
    if (!\defined('FONT')) {
        \define('FONT', uiFontFamily());
    }
}

/** "#1e3a8a" / 0x1e3a8a -> int */
function hx(string|int $hex): int
{
    if (\is_int($hex)) {
        return $hex;
    }

    return \hexdec(\ltrim($hex, '#'));
}

/** int hex -> 归一化 [r,g,b,a]（用于渐变 stop） */
function rgbN(int $hex, float $a = 1.0): array
{
    return [
        (($hex >> 16) & 0xFF) / 255.0,
        (($hex >> 8) & 0xFF) / 255.0,
        ($hex & 0xFF) / 255.0,
        $a,
    ];
}

/** 点是否在矩形内 */
function inside(array $r, float $x, float $y): bool
{
    return $x >= $r[0] && $x <= $r[0] + $r[2] && $y >= $r[1] && $y <= $r[1] + $r[3];
}

/**
 * 命中最顶层（最后绘制）的图形。
 *
 * 卡牌按 $hand 顺序从左到右绘制，后绘制的牌叠在先绘制的牌之上（重叠 28px）。
 * 点击重叠区域时，视觉上位于顶层的牌应是后者。因此必须**反向**遍历，
 * 让后绘制的（更靠右、更靠上）矩形优先匹配——这样点第一张牌露出部分选中第一张，
 * 点第二张牌盖住第一张的部分则选中第二张，符合直觉的层级关系。
 *
 * @param array<string|int,array> $rects 以绘制顺序为正序的命中矩形表
 */
function hitTopmost(array $rects, float $x, float $y): ?string
{
    $keys = \array_reverse(\array_keys($rects), true);
    foreach ($keys as $id) {
        if (inside($rects[$id], $x, $y)) {
            return (string) $id;
        }
    }

    return null;
}

/* ============================ Game Controller ============================ */

/** @var bool 开启调试日志（stdout 输出，方便排查卡死） */
const DEBUG_LOG = true;

final class GameController
{
    // Window geometry (fixed for the Win32 build).
    public const WIN_W = 960;
    public const WIN_H = 760;
    public const BTN_H = 64;   // reserved bottom action-bar height

    public Game $game;
    public int $human = 0;
    public string $mode = 'select';          // select | bid | play | over
    public string $message = '选择你的阵营与将领';
    /** @var list<string> */
    public array $selected = [];
    public ?int $pendingTarget = null;        // 选择技能目标中
    /** @var array<int,string> */
    public array $charIds = [0 => '', 1 => '', 2 => ''];
    public string $humanCharId = '';

    // ---- Win32 / TypePHP port state (replaces libui Area/Label/Button) ----
    public int $hWnd = 0;
    /** @var array<int,array{label:string,enabled:bool,cb:?callable}> */
    public array $actDefs = [];
    /** @var array<int,?array> index => [x,y,w,h] | null */
    public array $btnRects = [];
    public ?array $soundRect = null;
    public string $soundLabel = '🔊 音效';
    public bool $soundOn = true;
    public ?int $aiTimer = null;
    public ?int $bidTimer = null;

    /** @var array<string,array> 命中测试矩形 */
    public array $hit = ['hand' => [], 'opp' => [], 'select' => []];
    /** @var list<string> */
    public array $gameLog = [];

    /** 是否处于托管（自动出牌）模式 */
    public bool $autoPlay = false;

    /** 上次出牌的快照（lastPlay 被 pass 清空后仍保留，用于出牌区显示） */
    public ?array $lastShownPlay = null;

    /** 拖拽选牌状态：是否正在拖拽 */
    private bool $dragging = false;
    /** 拖拽模式：true=连选（把经过的牌加入），false=连消（把经过的牌移除） */
    private bool $dragModeSelect = true;
    /** @var array<string,bool> 本次拖拽已处理过的卡牌 id，避免重复切换造成闪烁 */
    private array $dragTouched = [];

    /** @var array<int,array{at:int,cb:callable,id:int}> */
    private array $timers = [];
    private int $timerSeq = 0;

    public function __construct()
    {
        $this->actDefs = [];
        $this->btnRects = [];
        $this->hit = ['hand' => [], 'opp' => [], 'select' => []];
        $this->gameLog = [];
        $this->soundLabel = '🔊 音效';
        $this->soundOn = true;
    }

    /* --------------------------- lifecycle --------------------------- */

    /** 调试日志：带时间戳写入 STDOUT。关闭时设 DEBUG_LOG = false 即可零开销。 */
    private static function dbg(string $msg): void
    {
        if (DEBUG_LOG) {
            \fwrite(STDOUT, '[' . \date('H:i:s') . '] [DDZ] ' . $msg . "\n");
        }
    }

    public function newGame(): void
    {
        $this->cancelTimers();
        $this->mode = 'select';
        $this->message = '选择你的阵营与将领，开始对局';
        $this->selected = [];
        $this->pendingTarget = null;
        $this->gameLog = [];
        $this->autoPlay = false;
        $this->lastShownPlay = null;
        $this->dragging = false;
        $this->dragTouched = [];
        $this->redraw();
    }

    /** @param array<int,string> $charIds */
    private function buildGame(array $charIds): void
    {
        $this->cancelTimers();
        $deal = Deck::deal();
        $players = [];
        foreach ($charIds as $i => $cid) {
            $c = Character::byId($cid);
            $ps = new PlayerState($deal['hands'][$i]);
            $ps->characterId = $c->id;
            $ps->faction = $c->faction;
            $ps->initSkill($c->skill());
            $players[] = $ps;
        }
        $this->charIds = $charIds;
        $this->humanCharId = $charIds[$this->human];
        $this->game = new Game($players, $deal['bottom']);
        $this->game->onEvent = function (string $event, $payload): void {
            $this->onGameEvent($event, $payload);
        };
        $this->selected = [];
        $this->pendingTarget = null;
        $this->gameLog = [];
    }

    public function startMatch(string $charId): void
    {
        self::dbg("startMatch(charId={$charId})");
        $this->autoPlay = false; // 每局开始重置托管状态
        $all = Character::all();
        $others = \array_values(\array_filter($all, static fn (Character $c): bool => $c->id !== $charId));
        \shuffle($others);
        $charIds = [$charId, $others[0]->id, $others[1]->id];
        self::dbg("  charIds=" . \implode(',', $charIds));
        $this->buildGame($charIds);
        $this->enterBidding();
    }

    private function redeal(): void
    {
        $this->buildGame($this->charIds);
    }

    /* ----------------------------- bidding --------------------------- */

    private function enterBidding(): void
    {
        self::dbg("enterBidding()");
        $this->mode = 'bid';
        $this->message = '叫地主阶段：你的回合，请选择叫分';
        $this->setBidActions();
        $this->redraw();
    }

    private function setBidActions(): void
    {
        $this->applyActions([
            ['重新选将', true, fn () => $this->newGame()],
            ['不叫', true, fn () => $this->humanBid(0)],
            ['1 分', true, fn () => $this->humanBid(1)],
            ['2 分', true, fn () => $this->humanBid(2)],
            ['3 分', true, fn () => $this->humanBid(3)],
        ]);
    }

    public function humanBid(int $score): void
    {
        self::dbg("humanBid(score={$score}) mode={$this->mode} current={$this->game->bidding['current']} human={$this->human}");
        if ($this->mode !== 'bid' || $this->game->bidding['current'] !== $this->human) {
            self::dbg("  → SKIP (not your turn or wrong mode)");

            return;
        }
        try {
            $res = $this->game->bid($this->human, $score);
        } catch (\Throwable $e) {
            self::dbg("  → EXCEPTION: " . get_class($e) . ': ' . $e->getMessage());
            $this->message = '叫分失败：' . $e->getMessage();

            return;
        }
        self::dbg("  → bid result='{$res}' highest={$this->game->bidding['highest']} acted=" . \json_encode($this->game->bidding['acted']));
        if ($res === 'started') {
            $this->enterPlaying();

            return;
        }
        if ($res === 'redeal') {
            $this->message = '无人叫地主，重新发牌';
            $this->redeal();
            $this->enterBidding();

            return;
        }
        // 'ok' —— 轮到 AI
        $this->scheduleBidStep();
    }

    private function scheduleBidStep(): void
    {
        self::dbg("scheduleBidStep() phase={$this->game->phase} current={$this->game->bidding['current']} human={$this->human}");
        if ($this->game->phase !== 'bidding') {
            self::dbg("  → SKIP (not in bidding phase)");

            return;
        }
        if ($this->game->bidding['current'] === $this->human) {
            self::dbg("  → human's turn, setBidActions");
            $this->setBidActions();
            $this->message = '请叫分';
            $this->redraw();

            return;
        }
        $p = $this->game->bidding['current'];
        self::dbg("  → AI player={$p} turn, scheduling tick...");
        $this->applyActions([['重新选将', true, fn () => $this->newGame()], ['对手叫分中…', false, null], ['', false, null], ['', false, null], ['', false, null]]);
        $this->message = '对手叫分中…';
        $this->redraw();
        // 用 delay() 驱动 AI 叫分：保留 ~700ms 节奏，避免对手瞬间叫完。
        $done = false;
        $doBid = function () use (&$done, $p): void {
            if ($done) {
                return;
            }
            $done = true;
            try {
                self::dbg("BID-STEP(tick) for player={$p} phase={$this->game->phase}");
                $aiScore = Ai::bid($this->game->players[$p]->hand);
                $res = $this->game->bid($p, $aiScore);
                self::dbg("  AI({$p}) bid={$aiScore} result='{$res}' highest={$this->game->bidding['highest']} acted=" . \json_encode($this->game->bidding['acted']));
                if ($res === 'started') {
                    self::dbg("  → enterPlaying()");
                    $this->enterPlaying();
                } elseif ($res === 'redeal') {
                    self::dbg("  → redeal + enterBidding()");
                    $this->message = '无人叫地主，重新发牌';
                    $this->redeal();
                    $this->enterBidding();
                } else {
                    self::dbg("  → scheduleBidStep() again");
                    $this->scheduleBidStep();
                }
            } catch (\Throwable $e) {
                self::dbg("  BID-STEP EXCEPTION: " . get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            }
        };
        $this->bidTimer = $this->delay(700, $doBid);
    }

    /* ------------------------------ play ----------------------------- */

    private function enterPlaying(): void
    {
        self::dbg("enterPlaying() landlord={$this->game->landlord}");
        $this->mode = 'play';
        $ll = $this->game->landlord;
        $name = Character::byId($this->game->players[$ll]->characterId)->name;
        if ($ll === $this->human) {
            $this->message = '你成为了地主！';
            self::dbg("  → YOU are landlord!");
        } else {
            $this->message = '地主：' . $name . ' —— 你是' . ($this->game->side($this->human) === 'landlord' ? '地主' : '农民');
            self::dbg("  → landlord is {$name}, you are " . ($this->game->side($this->human) === 'landlord' ? 'landlord' : 'farmer'));
        }
        $this->scheduleAi();
    }

    private function scheduleAi(): void
    {
        self::dbg("scheduleAi() phase={$this->game->phase} turn={$this->game->turn} human={$this->human} isOver={$this->game->isOver()}");
        if ($this->game->isOver()) {
            self::dbg("  → game over, endGame");
            $this->endGame();

            return;
        }
        if ($this->game->phase !== 'playing') {
            self::dbg("  → SKIP (not in playing phase)");

            return;
        }
        if ($this->game->turn === $this->human) {
            self::dbg("  → human's turn, humanTurn()");
            $this->humanTurn();

            return;
        }
        $this->applyActions([['重新选将', true, fn () => $this->newGame()], ['对手行动中…', false, null], ['', false, null], ['', false, null], ['', false, null]]);
        $this->message = '对手行动中…';
        $this->redraw();
        // 用 delay() 驱动 AI 走子：Win32 移植版里 defer 与 delay 都由主循环
        // tick() 触发，机制相同；这里只用 delay 保留思考节奏（~800ms），
        // 避免 AI 瞬间行动、玩家跟不上牌局。$moved 防重复触发。
        $moved = false;
        $doMove = function () use (&$moved): void {
            if ($moved) {
                return;
            }
            $moved = true;
            try {
                self::dbg("AI-STEP(tick) turn={$this->game->turn} phase={$this->game->phase}");
                $this->aiStep();
            } catch (\Throwable $e) {
                self::dbg("  AI-STEP EXCEPTION: " . get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            }
        };
        $this->aiTimer = $this->delay(800, $doMove);
    }

    public function aiStep(): void
    {
        self::dbg("aiStep() turn={$this->game->turn} phase={$this->game->phase} isOver={$this->game->isOver()}");
        if ($this->game->isOver()) {
            self::dbg("  → endGame");
            $this->endGame();

            return;
        }
        if ($this->game->turn === $this->human) {
            self::dbg("  → human's turn, humanTurn()");
            $this->humanTurn();

            return;
        }
        $actor = $this->game->turn;
        self::dbg("  → AI({actor}) acting...");
        Ai::act($this->game, $actor);
        $this->tryAiCounterBomb();
        $this->redraw();
        self::dbg("  → AI({actor}) done, isOver={$this->game->isOver()}");
        if ($this->game->isOver()) {
            $this->endGame();

            return;
        }
        $this->scheduleAi();
    }

    /** AI 中的赤犬（akainu）可对刚出的炸弹发动反击。 */
    private function tryAiCounterBomb(): void
    {
        if ($this->game->lastPlay === null || !$this->game->lastPlay['combo']->isBomb) {
            return;
        }
        foreach ([1, 2] as $p) {
            if ($p === $this->human) {
                continue;
            }
            if (Ai::maybeCounter($this->game, $p)) {
                return;
            }
        }
    }

    private function humanTurn(): void
    {
        self::dbg("humanTurn() mode={$this->mode} isOver={$this->game->isOver()}");
        if ($this->game->isOver()) {
            self::dbg("  → endGame");
            $this->endGame();

            return;
        }
        $this->message = $this->pendingTarget !== null ? '请点击一名对手作为技能目标' : '你的回合';
        $this->setActionsForHumanTurn();
        $this->redraw();
    }

    private function setActionsForHumanTurn(): void
    {
        $moves = $this->game->legalMoves($this->human);
        $playEnabled = $this->game->lastPlay === null || $moves !== [];
        $passEnabled = $this->game->lastPlay !== null;

        if ($this->pendingTarget !== null) {
            $playEnabled = false;
            $passEnabled = false;
        }

        $skillLabel = '技能';
        $skillEnabled = false;
        $skillCb = null;
        if ($this->humanCharId === 'akainu') {
            $canCounter = $this->game->lastPlay !== null
                && $this->game->lastPlay['combo']->isBomb
                && $this->game->canUse($this->human, Character::byId('akainu')->skill());
            $skillLabel = $canCounter ? '🔥岩浆反击' : '技能(被动)';
            $skillEnabled = $canCounter;
            $skillCb = $canCounter ? fn () => $this->counterNow() : null;
        } elseif ($this->pendingTarget !== null) {
            $skillLabel = '取消';
            $skillEnabled = true;
            $skillCb = fn () => $this->cancelTarget();
        } else {
            $skill = Character::byId($this->humanCharId)->skill();
            $skillEnabled = $this->game->canUse($this->human, $skill);
            $skillCb = fn () => $this->useSkill();
        }

        $this->applyActions([
            ['重新选将', true, fn () => $this->newGame()],
            ['出牌', $playEnabled && !$this->autoPlay, fn () => $this->humanPlay()],
            ['不出', $passEnabled && !$this->autoPlay, fn () => $this->humanPass()],
            ['提示', true && !$this->autoPlay, fn () => $this->hint()],
            [$skillLabel, $skillEnabled && !$this->autoPlay, $skillCb],
            [$this->autoPlay ? '取消托管' : '托管', true, fn () => $this->toggleAutoPlay()],
        ]);

        // 托管模式：延迟约 1.5s 后自动出牌（类人速度，给玩家充足时间点「取消托管」）
        if ($this->autoPlay) {
            $this->message = '托管中…';
            $this->redraw();
            $done = false;
            $step = function () use (&$done): void {
                if ($done) {
                    return;
                }
                $done = true;
                $this->autoPlayStep();
            };
            $this->aiTimer = $this->delay(1500, $step);
        }
    }

    /** 执行一次托管自动出牌 */
    public function autoPlayStep(): void
    {
        if ($this->mode !== 'play' || $this->game->isOver()) {
            return;
        }
        if ($this->game->turn !== $this->human || !$this->autoPlay) {
            return;
        }

        self::dbg("AUTO-PLAY: executing AI move for human");

        try {
            // 复用 AI 决策逻辑
            $moves = $this->game->legalMoves($this->human);
            if ($this->game->lastPlay === null) {
                // 领出：用 pickLead 选牌
                $combo = Ai::pickLead($this->game, $this->human, $moves) ?? ($moves[0] ?? null);
            } else {
                // 跟出：用 pickFollow 选牌或 pass
                $combo = Ai::pickFollow($this->game, $this->human, $moves);
            }

            if ($combo !== null) {
                $cards = $combo->cards;
                $this->game->play($this->human, $cards);
                self::dbg("AUTO-PLAY: played " . $combo->describe());
            } else {
                $this->game->pass($this->human);
                self::dbg("AUTO-PLAY: passed");
            }
        } catch (\Throwable $e) {
            self::dbg("AUTO-PLAY ERROR: " . get_class($e) . ': ' . $e->getMessage());
        }

        $this->redraw();
        if (!$this->game->isOver()) {
            $this->scheduleAi();
        } else {
            $this->endGame();
        }
    }

    /** 切换托管状态 */
    public function toggleAutoPlay(): void
    {
        $this->autoPlay = !$this->autoPlay;
        self::dbg("AUTO-PLAY toggle: " . ($this->autoPlay ? 'ON' : 'OFF'));
        if (!$this->autoPlay) {
            // 关闭托管：立即取消挂起的托管自动出牌定时器，玩家可手动接管
            $this->cancelTimers();
            // 重新计算按钮状态：托管时「出牌/不出/提示/技能」被禁用，
            // 恢复后必须重算才能重新可点（仅轮到玩家且在出牌阶段时）
            if ($this->mode === 'play' && $this->game->turn === $this->human && !$this->game->isOver()) {
                $this->setActionsForHumanTurn();
            }
            $this->redraw();

            return;
        }
        if ($this->mode === 'play' && $this->game->turn === $this->human && $this->autoPlay) {
            // 打开托管且轮到玩家：稍后自动出牌（1.5s 后），给玩家取消的机会
            $this->message = '托管中…';
            $this->setActionsForHumanTurn();
        } else {
            $this->redraw();
        }
    }

    private function applyActions(array $defs): void
    {
        $n = \count($defs);
        for ($i = 0; $i < 6; $i++) {
            if ($i < $n) {
                $d = $defs[$i];
                $this->actDefs[$i] = ['label' => $d[0], 'enabled' => (bool) $d[1], 'cb' => $d[2]];
            } else {
                $this->actDefs[$i] = ['label' => '', 'enabled' => false, 'cb' => null];
            }
        }
    }

    /** @return list<Card> */
    private function selectedCards(): array
    {
        $map = [];
        foreach ($this->game->players[$this->human]->hand as $c) {
            $map[$c->id()] = $c;
        }
        $out = [];
        foreach ($this->selected as $id) {
            if (isset($map[$id])) {
                $out[] = $map[$id];
            }
        }

        return $out;
    }

    public function humanPlay(): void
    {
        $cards = $this->selectedCards();
        if ($cards === []) {
            $this->message = '请先点击选择要出的牌';

            return;
        }
        try {
            $this->game->play($this->human, $cards);
        } catch (\Throwable $e) {
            $this->message = '出牌无效：' . $e->getMessage();
            $this->redraw();

            return;
        }
        $this->selected = [];
        $this->tryAiCounterBomb();
        if ($this->game->isOver()) {
            $this->endGame();

            return;
        }
        $this->scheduleAi();
    }

    public function humanPass(): void
    {
        if ($this->game->lastPlay === null) {
            $this->message = '你是首出，不能不出';
            $this->redraw();

            return;
        }
        try {
            $this->game->pass($this->human);
        } catch (\Throwable $e) {
            $this->message = '无法不出：' . $e->getMessage();
            $this->redraw();

            return;
        }
        $this->selected = [];
        if ($this->game->isOver()) {
            $this->endGame();

            return;
        }
        $this->scheduleAi();
    }

    public function hint(): void
    {
        if ($this->game->turn !== $this->human) {
            return;
        }
        $moves = $this->game->legalMoves($this->human);
        if ($moves === []) {
            $this->message = '没有能出的牌，请点「不出」';
            $this->redraw();

            return;
        }
        $mv = $this->game->lastPlay === null
            ? Ai::pickLead($this->game, $this->human, $moves)
            : Ai::pickFollow($this->game, $this->human, $moves);
        if ($mv === null) {
            $this->message = '没有能出的牌，请点「不出」';
            $this->redraw();

            return;
        }
        $this->selected = \array_map(static fn (Card $c): string => $c->id(), $mv->cards);
        $this->message = '已为你选好一手，点击「出牌」';
        $this->setActionsForHumanTurn();
        $this->redraw();
    }

    public function useSkill(): void
    {
        $skill = Character::byId($this->humanCharId)->skill();
        if ($skill->trigger === 'onTurnStart') {
            $needsTarget = \in_array($skill->id, ['aokiji_freeze', 'boa_petrify', 'kuma_push', 'bigmom_steal'], true);
            if ($needsTarget && $this->pendingTarget === null) {
                $this->pendingTarget = -1;
                $this->message = '请点击一名对手作为技能目标';
                $this->setActionsForHumanTurn();
                $this->redraw();

                return;
            }
            $target = $this->pendingTarget !== null && $this->pendingTarget >= 0 ? $this->pendingTarget : null;
            try {
                $this->game->armSkill($this->human, $target);
            } catch (\Throwable $e) {
                $this->message = '技能失败：' . $e->getMessage();
                $this->redraw();

                return;
            }
            $this->pendingTarget = null;
            $this->message = '技能已发动：' . $skill->name;
        } else {
            if ($this->humanCharId === 'akainu') {
                return;
            }
            try {
                $this->game->armSkill($this->human);
            } catch (\Throwable $e) {
                $this->message = '技能失败：' . $e->getMessage();
                $this->redraw();

                return;
            }
            $this->message = '技能已预置：' . $skill->name . '（下一手生效）';
        }
        $this->setActionsForHumanTurn();
        $this->redraw();
    }

    public function counterNow(): void
    {
        try {
            $this->game->counterBomb($this->human);
        } catch (\Throwable $e) {
            $this->message = '反击失败：' . $e->getMessage();
            $this->redraw();

            return;
        }
        $this->message = '岩浆反击！获得出牌权';
        if ($this->game->isOver()) {
            $this->endGame();

            return;
        }
        $this->humanTurn();
    }

    public function armWithTarget(int $p): void
    {
        $this->pendingTarget = $p;
        $this->useSkill();
    }

    public function cancelTarget(): void
    {
        $this->pendingTarget = null;
        $this->message = '你的回合';
        $this->setActionsForHumanTurn();
        $this->redraw();
    }

    public function toggleSound(): void
    {
        $this->soundOn = !$this->soundOn;
        Sound::instance()->setEnabled($this->soundOn);
        $this->soundLabel = $this->soundOn ? '🔊 音效' : '🔇 静音';
    }

    private function endGame(): void
    {
        self::dbg("endGame() mode={$this->mode}");
        if ($this->mode === 'over') {
            return;
        }
        $this->mode = 'over';
        $this->cancelTimers();
        $this->autoPlay = false; // 结束时取消托管状态
        $side = $this->game->winnerSide;
        $humanSide = $this->game->side($this->human);
        $this->message = ($side === $humanSide ? '🎉 胜利！' : '💀 战败')
            . '（' . ($side === 'landlord' ? '地主' : '农民') . '方获胜）';
        $this->applyActions([
            ['重新选将', true, fn () => $this->newGame()],
            ['再来一局', true, fn () => $this->startMatch($this->humanCharId)],
        ]);
        $this->redraw();
    }

    public function cancelTimers(): void
    {
        if ($this->aiTimer !== null) {
            try {
                $this->cancelTimer($this->aiTimer);
            } catch (\Throwable) {
            }
            $this->aiTimer = null;
        }
        if ($this->bidTimer !== null) {
            try {
                $this->cancelTimer($this->bidTimer);
            } catch (\Throwable) {
            }
            $this->bidTimer = null;
        }
    }

    /* ----------------------------- timers ----------------------------- */

    private function defer(callable $cb): void
    {
        $this->timers[] = ['at' => win_get_tick_count(), 'cb' => $cb, 'id' => ++$this->timerSeq];
    }

    private function delay(int $ms, callable $cb): int
    {
        $id = ++$this->timerSeq;
        $this->timers[] = ['at' => win_get_tick_count() + $ms, 'cb' => $cb, 'id' => $id];

        return $id;
    }

    private function cancelTimer(int $id): void
    {
        foreach ($this->timers as $k => $t) {
            if ($t['id'] === $id) {
                unset($this->timers[$k]);
            }
        }
        $this->timers = \array_values($this->timers);
    }

    /** Fire all timers whose deadline has passed. Called once per frame. */
    public function tick(): void
    {
        $now = win_get_tick_count();
        $fired = [];
        foreach ($this->timers as $k => $t) {
            if ($t['at'] <= $now) {
                $fired[] = $t['cb'];
                unset($this->timers[$k]);
            }
        }
        $this->timers = \array_values($this->timers);
        foreach ($fired as $cb) {
            ($cb)();
        }
    }

    /* --------------------------- input buttons -------------------------- */

    /** Returns true if an (enabled) action button was hit at (x, y). */
    private function handleButtonClick(float $x, float $y): bool
    {
        foreach ($this->btnRects as $i => $r) {
            if ($r === null) {
                continue;
            }
            if (inside($r, $x, $y)) {
                $d = $this->actDefs[$i] ?? null;
                if ($d !== null && $d['enabled'] && $d['cb'] !== null) {
                    ($d['cb'])();
                }

                return true;
            }
        }

        return false;
    }

    /** Draw the bottom action bar (6 action buttons) + the sound toggle. */
    private function drawButtons(WinDrawContext $ctx, float $W, float $H): void
    {
        $by = (float) ($H - self::BTN_H + 12);
        $h = 40.0;
        $margin = 16.0;
        $gap = 10.0;
        $count = 6;
        $bw = ($W - 2 * $margin - ($count - 1) * $gap) / $count;
        $x = $margin;
        $this->btnRects = [];
        for ($i = 0; $i < $count; $i++) {
            $d = $this->actDefs[$i] ?? null;
            if ($d === null || $d['label'] === '') {
                $this->btnRects[$i] = null;
                $x += $bw + $gap;
                continue;
            }
            $this->btnRects[$i] = [$x, $by, $bw, $h];
            $col = $d['enabled'] ? 0x2563eb : 0x334155;
            $ctx->fillRoundedRect($x, $by, $bw, $h, 8.0, Brush::rgb($col));
            $ctx->drawString(
                $d['label'],
                new FontDescriptor(FONT, 14, TextWeight::Bold),
                Color::rgb(0xffffff),
                $x, $by + ($h - 14.0) / 2.0,
                (int) $bw,
                DrawTextAlign::Center
            );
            $x += $bw + $gap;
        }

        // 音效开关（右上角）
        $sx = (float) ($W - 110);
        $sy = 10.0;
        $sw = 96.0;
        $sh = 28.0;
        $this->soundRect = [$sx, $sy, $sw, $sh];
        $ctx->fillRoundedRect($sx, $sy, $sw, $sh, 6.0, Brush::rgb($this->soundOn ? 0x1f2937 : 0x374151));
        $ctx->drawString(
            $this->soundLabel,
            new FontDescriptor(FONT, 13, TextWeight::Bold),
            Color::rgb(0xe2e8f0),
            $sx, $sy + 6.0,
            (int) $sw,
            DrawTextAlign::Center
        );
    }

    /* --------------------------- event hook -------------------------- */

    private function onGameEvent(string $event, $payload): void
    {
        // ---- SFX 触发 ----
        $s = Sound::instance();
        switch ($event) {
            case 'bid':
                $s->trigger('bid');
                break;
            case 'gameStart':
                $s->trigger('deal');
                break;
            case 'play':
                $combo = $payload['combo'];
                $s->trigger($combo->isBomb || $combo->isRocket ? 'bomb' : 'play');
                // 保存出牌快照，供 lastPlay 被 pass 清空后回退显示
                $this->lastShownPlay = [
                    'combo' => $payload['combo'],
                    'player' => $payload['player'],
                    'hakiActive' => $payload['hakiActive'] ?? false,
                    'unblockable' => $payload['unblockable'] ?? false,
                ];
                break;
            case 'pass':
                $s->trigger('pass');
                break;
            case 'skill':
            case 'arm':
                $s->trigger('skill');
                break;
            case 'counter':
                $s->trigger('bomb');
                break;
            case 'gameOver':
                $humanSide = $this->game->side($this->human);
                $s->trigger($payload['side'] === $humanSide ? 'win' : 'lose');
                break;
        }

        // ---- 可读中文日志 ----
        $msg = $this->formatLogLine($event, $payload);
        if ($msg !== null) {
            $this->gameLog[] = $msg;
            $this->gameLog = \array_slice($this->gameLog, -6); // 最多保留 6 行
        }
        $this->redraw();
    }

    /** 将游戏事件格式化为中文日志行，返回 null 时不记录。 */
    private function formatLogLine(string $event, $payload): ?string
    {
        $pname = function (int $idx): string {
            if (!isset($this->game->players[$idx])) {
                return "玩家{$idx}";
            }
            $cid = $this->game->players[$idx]->characterId;
            return Character::byId($cid)->name;
        };
        switch ($event) {
            case 'bid':
                $score = $payload['score'];
                $txt = $score > 0 ? "叫了 {$score} 分" : '不叫';
                return "{$pname($payload['player'])} {$txt}";
            case 'gameStart':
                return "🎮 地主：{$pname($payload['landlord'])}";
            case 'play':
                $c = $payload['combo'];
                return "{$pname($payload['player'])} 出了 {$c->describe()}";
            case 'pass':
                return "{$pname($payload['player'])} 要不起";
            case 'counter':
                return "💥 {$pname($payload['player'])} 反炸了 {$pname($payload['target'])}";
            case 'skill':
                return "⚡ {$pname($payload['player'])} 发动技能";
            case 'arm':
                return "🔥 {$pname($payload['player'])} 蓄力中";
            case 'skip':
                return "❄️ {$pname($payload['player'])} 被跳过";
            case 'frozenSkip':
                return "🧊 {$pname($payload['player'])} 被冰冻";
            case 'gameOver':
                $side = $payload['side'] === 'landlord' ? '地主' : '农民';
                return "🏆 {$pname($payload['winner'])} 获胜（{$side}）";
            default:
                return "[{$event}]";
        }
    }

    /* ----------------------------- input ----------------------------- */

    public function onMouse(object $e): void
    {
        $x = (float) ($e->x ?? 0);
        $y = (float) ($e->y ?? 0);
        $down = (int) ($e->down ?? 0);
        $up = (int) ($e->up ?? 0);
        $held = (int) ($e->held ?? 0);

        // 音效开关（右上角，始终可点）
        if ($down === 1 && $up === 0 && $this->soundRect !== null
            && inside($this->soundRect, $x, $y)) {
            $this->toggleSound();

            return;
        }

        if ($this->mode === 'select') {
            if ($down === 1 && $up === 0) {
                $id = hitTopmost($this->hit['select'], $x, $y);
                if ($id !== null) {
                    $this->startMatch($id);
                }
            }

            return;
        }

        if ($this->mode === 'over') {
            if ($down === 1 && $up === 0) {
                $this->handleButtonClick($x, $y);
            }

            return;
        }

        // bid / play：先处理底部动作按钮（含技能取消 / 重新选将）
        if ($down === 1 && $up === 0) {
            if ($this->handleButtonClick($x, $y)) {
                return;
            }
        }

        if ($this->mode === 'play') {
            // 技能指定目标：仅在左键按下时响应
            if ($this->pendingTarget !== null) {
                if ($down === 1 && $up === 0) {
                    foreach ([1, 2] as $p) {
                        if (isset($this->hit['opp'][$p]) && inside($this->hit['opp'][$p], $x, $y)) {
                            $this->armWithTarget($p);

                            return;
                        }
                    }
                }

                return;
            }

            if ($this->game->turn === $this->human && $this->game->phase === 'playing') {
                $engaged = $down !== 0 || $held !== 0;
                $released = $up !== 0;
                // 松开鼠标：结束本次拖拽
                if ($released) {
                    $this->endDrag();

                    return;
                }
                // 纯悬停（无任何按键按住）：不做选牌
                if (!$engaged) {
                    return;
                }
                $id = hitTopmost($this->hit['hand'], $x, $y);
                if ($id === null) {
                    // 拖拽过程中移出手牌区域：保持拖拽状态，不处理
                    return;
                }
                if (!$this->dragging) {
                    // 拖拽起点：以首张牌当前状态决定本次拖拽是「连选」还是「连消」
                    $this->dragging = true;
                    $this->dragTouched = [];
                    $this->dragModeSelect = !\in_array($id, $this->selected, true);
                    $this->applyDrag($id);
                } else {
                    $this->applyDrag($id);
                }
            }
        }
    }

    /** 拖拽选牌：将一张牌按当前拖拽模式加入/移出选中集合（同一次拖拽每个 id 只处理一次）。 */
    private function applyDrag(string $id): void
    {
        if (isset($this->dragTouched[$id])) {
            return;
        }
        $this->dragTouched[$id] = true;

        if ($this->dragModeSelect) {
            if (!\in_array($id, $this->selected, true)) {
                $this->selected[] = $id;
                $this->message = '已选 ' . \count($this->selected) . ' 张';
                $this->redraw();
            }
        } else {
            $i = \array_search($id, $this->selected, true);
            if ($i !== false) {
                unset($this->selected[$i]);
                $this->selected = \array_values($this->selected);
                $this->message = '已选 ' . \count($this->selected) . ' 张';
                $this->redraw();
            }
        }
    }

    /** 结束拖拽，清空本次拖拽的临时状态。 */
    private function endDrag(): void
    {
        $this->dragging = false;
        $this->dragTouched = [];
    }

    public function onKey(int $vk, int $up = 0): bool
    {
        if ($up !== 0 || $this->mode !== 'play' || $this->game->turn !== $this->human) {
            return false;
        }
        if ($vk === 13) { // Enter / Return
            if ($this->selected !== []) {
                $this->humanPlay();
            }

            return true;
        }
        if ($vk === 80 || $vk === 112) { // p / P
            $this->humanPass();

            return true;
        }
        if ($vk === 72 || $vk === 104) { // h / H
            $this->hint();

            return true;
        }

        return false;
    }

    public function redraw(): void
    {
        // Win32 rendering is continuous (the message loop calls render() every
        // frame), so a redraw request is a no-op here. The message text is read
        // directly by render().
    }

    /* ---------------------------- rendering --------------------------- */

    public function render(): void
    {
        if ($this->hWnd === 0) {
            return;
        }
        $hdc = win_begin_paint($this->hWnd);
        $ctx = new WinDrawContext($hdc);

        // 支持窗口缩放：每帧读取实际客户区尺寸进行自适应布局
        $sz = win_get_client_size($this->hWnd);
        $W = (float) ($sz[0] > 0 ? $sz[0] : self::WIN_W);
        $H = (float) ($sz[1] > 0 ? $sz[1] : self::WIN_H);
        $gh = $H - self::BTN_H; // game-drawing height; bottom strip is the action bar

        $this->hit = ['hand' => [], 'opp' => [], 'select' => []];

        $this->drawBackground($ctx, $W, $gh);

        if ($this->mode === 'select' || !isset($this->game)) {
            $this->drawSelectScreen($ctx, $W, $gh);
        } elseif ($this->mode === 'over') {
            // 仅绘制背景 + 结算浮层，不绘制牌桌/日志（避免穿透）
            $this->drawOver($ctx, $W, $gh);
        } else {
            $this->drawBoard($ctx, $W, $gh);
        }

        $this->drawButtons($ctx, $W, $H);

        win_end_paint($this->hWnd, $hdc);
    }

    private function drawBackground(WinDrawContext $ctx, float $W, float $H): void
    {
        try {
            $g = Brush::linearGradient(0.0, 0.0, 0.0, $H, [
                [0.0, ...rgbN(0x0b1a3a)],
                [0.55, ...rgbN(0x08152e)],
                [1.0, ...rgbN(0x050d1f)],
            ]);
            $ctx->fillRect(0.0, 0.0, $W, $H, $g);
        } catch (\Throwable) {
            $ctx->fillRect(0.0, 0.0, $W, $H, Brush::rgb(0x071025));
        }
        $ctx->fillEllipse($W * 0.5, $H * 0.12, $W * 0.55, $H * 0.18,
            Brush::color(Color::rgba(0.25, 0.45, 0.8, 0.10)));
    }

    private function title(WinDrawContext $ctx, float $W): void
    {
        $f = new FontDescriptor(FONT, 18, TextWeight::Bold);
        $ctx->drawString('海贼王 · 斗地主', $f, Color::rgb(0xe2e8f0), 16.0, 12.0);
    }

    private function drawBoard(WinDrawContext $ctx, float $W, float $H): void
    {
        $this->title($ctx, $W);

        $top = 44.0;
        $oppY = $top;
        $oppW = \min(320.0, ($W - 48.0) / 2.0);
        $oppH = 132.0;
        $this->drawOpponent($ctx, 16.0, $oppY, $oppW, $oppH, 1);
        $this->drawOpponent($ctx, $W - 16.0 - $oppW, $oppY, $oppW, $oppH, 2);

        $handH = 132.0;
        $handTop = $H - $handH - 8.0;
        $this->drawPlayArea($ctx, $W, $oppY + $oppH + 8.0, $handTop - ($oppY + $oppH + 8.0));
        $this->drawHand($ctx, $W, $handTop, $handH);

        // 日志放在 banner 上方、手牌上方，不与出牌区/手牌重叠
        $this->drawLog($ctx, $W, $handTop - 80.0);
        $this->drawBanner($ctx, $W, $handTop - 26.0);
    }

    private function factionColor(string $faction): int
    {
        return hx(Faction::color($faction));
    }

    private function drawOpponent(WinDrawContext $ctx, float $x, float $y, float $w, float $h, int $p): void
    {
        $this->hit['opp'][$p] = [$x, $y, $w, $h];
        $ps = $this->game->players[$p];
        $char = Character::byId($ps->characterId);
        $fc = $this->factionColor($ps->faction);
        $isTurn = $this->game->turn === $p && $this->game->phase === 'playing';

        $ctx->fillRoundedRect($x, $y, $w, $h, 12.0,
            Brush::color(Color::rgba(0.05, 0.10, 0.22, 0.85)));
        $ctx->strokeRoundedRect($x, $y, $w, $h, 12.0,
            $isTurn ? Brush::rgb($fc) : Brush::color(Color::rgba(1, 1, 1, 0.12)),
            (new StrokeParams())->thickness($isTurn ? 2.5 : 1.0));

        $ctx->fillRoundedRect($x, $y, 8.0, $h, 4.0, Brush::rgb($fc));

        $fName = new FontDescriptor(FONT, 15, TextWeight::Bold);
        $fSmall = new FontDescriptor(FONT, 11);
        $fTiny = new FontDescriptor(FONT, 10);

        $ctx->drawString($char->name, $fName, Color::rgb(0xf1f5f9), $x + 16.0, $y + 10.0);
        $ctx->drawString(Faction::name($ps->faction) . ' · ' . $char->title, $fSmall,
            Color::rgba(0.85, 0.9, 1.0, 0.85), $x + 16.0, $y + 32.0);

        $crown = $ps->isLandlord ? ' 👑' : '';
        $ctx->drawString('手牌 ' . $this->game->handCount($p) . $crown, $fSmall,
            Color::rgb(0xcbd5e1), $x + 16.0, $y + 52.0);

        $badges = [];
        if ($ps->armed !== null) {
            $badges[] = '技';
        }
        if ($this->game->mods['frozen'][$p] > 0) {
            $badges[] = '冻';
        }
        if ($this->game->mods['petrified'][$p] > 0) {
            $badges[] = '石';
        }
        if ($this->game->mods['bombDisabled'][$p] > 0) {
            $badges[] = '禁';
        }
        if ($badges !== []) {
            $ctx->drawString('状态: ' . \implode(' ', $badges), $fTiny, Color::rgb(0xfbbf24),
                $x + 16.0, $y + 72.0);
        }

        $n = $this->game->handCount($p);
        $bw = 26.0;
        $bh = 38.0;
        $gap = 9.0;
        $total = $bw + \max(0, $n - 1) * $gap;
        $sx = $x + 16.0;
        $sy = $y + $h - $bh - 8.0;
        $draw = \min($n, 18);
        for ($i = 0; $i < $draw; $i++) {
            $this->drawCardBack($ctx, $sx + $i * $gap, $sy, $bw, $bh, $fc);
        }
        if ($n > 18) {
            $ctx->drawString('+' . ($n - 18), $fTiny, Color::rgb(0x94a3b8),
                $sx + 18 * $gap + 4.0, $sy + 12.0);
        }
    }

    private function drawCardBack(WinDrawContext $ctx, float $x, float $y, float $w, float $h, int $fc): void
    {
        $r = 6.0;
        $ctx->fillRoundedRect($x, $y, $w, $h, $r, Brush::rgb($fc));
        $ctx->strokeRoundedRect($x, $y, $w, $h, $r, Brush::color(Color::rgba(1, 1, 1, 0.35)),
            (new StrokeParams())->thickness(1.0));
        $ctx->fillRoundedRect($x + 4.0, $y + 4.0, $w - 8.0, $h - 8.0, 4.0,
            Brush::color(Color::rgba(1, 1, 1, 0.12)));
    }

    private function drawPlayArea(WinDrawContext $ctx, float $W, float $y, float $h): void
    {
        // 优先显示 lastPlay，若被 pass 清空则回退到 lastShownPlay（保留上次出牌快照）
        $lp = $this->game->lastPlay ?? $this->lastShownPlay;
        if ($lp === null) {
            $f = new FontDescriptor(FONT, 14);
            $ctx->drawString('等待首出…', $f, Color::rgb(0x94a3b8), 0.0, $y + $h / 2 - 8.0, $W,
                DrawTextAlign::Center);

            return;
        }
        $combo = $lp['combo'];
        $actor = $lp['player'];
        $char = Character::byId($this->game->players[$actor]->characterId);

        $fSmall = new FontDescriptor(FONT, 13);
        $label = ($actor === $this->human ? '你' : $char->name) . ' 出了 ' . $combo->describe();
        if ($lp['hakiActive']) {
            $label .= ' 【霸王色】';
        }
        if ($lp['unblockable']) {
            $label .= ' 【不可拦截】';
        }
        $ctx->drawString($label, $fSmall, Color::rgb(0xe2e8f0), 0.0, $y + 6.0, $W,
            DrawTextAlign::Center);

        $cards = $combo->cards;
        $cw = 46.0;
        $ch = 64.0;
        $gap = 8.0;
        $total = \count($cards) * ($cw + $gap) - $gap;
        $sx = ($W - $total) / 2.0;
        $cy = $y + 28.0;
        foreach ($cards as $i => $c) {
            $this->drawMiniCard($ctx, $sx + $i * ($cw + $gap), $cy, $cw, $ch, $c);
        }
    }

    private function drawMiniCard(WinDrawContext $ctx, float $x, float $y, float $w, float $h, Card $c): void
    {
        $r = 6.0;
        $ctx->fillRoundedRect($x, $y, $w, $h, $r, Brush::rgb(0xf8fafc));
        $ctx->strokeRoundedRect($x, $y, $w, $h, $r, Brush::rgb(0xcbd5e1),
            (new StrokeParams())->thickness(1.0));
        $isRed = \in_array($c->suit, ['♥', '♦'], true);
        $col = $c->isJoker() ? ($c->rank === Card::JOKER_BIG ? 0xb45309 : 0x475569)
            : ($isRed ? 0xdc2626 : 0x0f172a);
        $fRank = new FontDescriptor(FONT, 16, TextWeight::Bold);
        $fSuit = new FontDescriptor(FONT, 13);
        if ($c->isJoker()) {
            $ctx->drawString($c->rank === Card::JOKER_BIG ? '大' : '小', $fRank, Color::rgb($col),
                $x, $y + 6.0, $w, DrawTextAlign::Center);
            $ctx->drawString('★', $fSuit, Color::rgb($col), $x, $y + $h - 24.0, $w, DrawTextAlign::Center);
        } else {
            $ctx->drawString($c->label(), $fRank, Color::rgb($col), $x, $y + 6.0, $w, DrawTextAlign::Center);
            $ctx->drawString($c->suit, $fSuit, Color::rgb($col), $x, $y + $h - 24.0, $w, DrawTextAlign::Center);
        }
    }

    private function drawHand(WinDrawContext $ctx, float $W, float $handTop, float $handH): void
    {
        $hand = $this->game->players[$this->human]->hand;
        $cw = 68.0;
        $ch = 90.0;
        $raise = 18.0;
        $gap = 36.0;
        $n = \count($hand);
        if ($n === 0) {
            return;
        }
        $total = $cw + ($n - 1) * $gap;
        $sx = ($W - $total) / 2.0;

        foreach ($hand as $i => $c) {
            $id = $c->id();
            $sel = \in_array($id, $this->selected, true);
            $cx = $sx + $i * $gap;
            $cy = $handTop + ($sel ? 0.0 : $raise);
            $this->hit['hand'][$id] = [$cx, $cy, $cw, $ch];
            $this->drawCardFace($ctx, $cx, $cy, $cw, $ch, $c, $sel);
        }
    }

    private function drawCardFace(WinDrawContext $ctx, float $x, float $y, float $w, float $h, Card $c, bool $sel): void
    {
        $r = 8.0;
        $ctx->fillRoundedRect($x + 2.0, $y + 4.0, $w, $h, $r, Brush::color(Color::rgba(0, 0, 0, 0.30)));
        $body = $sel ? Brush::rgb(0xfff7ed) : Brush::rgb(0xf8fafc);
        $ctx->fillRoundedRect($x, $y, $w, $h, $r, $body);
        $border = $sel ? Brush::rgb(0xf59e0b) : Brush::rgb(0xcbd5e1);
        $ctx->strokeRoundedRect($x, $y, $w, $h, $r, $border, (new StrokeParams())->thickness($sel ? 3.0 : 1.5));

        $isRed = \in_array($c->suit, ['♥', '♦'], true);
        $col = $c->isJoker() ? ($c->rank === Card::JOKER_BIG ? 0xb45309 : 0x475569)
            : ($isRed ? 0xdc2626 : 0x0f172a);

        $fRank = new FontDescriptor(FONT, 16, TextWeight::Bold);
        $fMid = new FontDescriptor(FONT, 22, TextWeight::Bold);
        $fSuit = new FontDescriptor(FONT, 14);

        if ($c->isJoker()) {
            $ctx->drawString($c->rank === Card::JOKER_BIG ? '大' : '小', $fRank, Color::rgb($col),
                $x + 6.0, $y + 6.0);
            $ctx->drawString('★', $fMid, Color::rgb($col), $x, $y + $h * 0.30, $w, DrawTextAlign::Center);
            $ctx->drawString('JOKER', new FontDescriptor(FONT, 9), Color::rgb($col),
                $x, $y + $h - 16.0, $w, DrawTextAlign::Center);
        } else {
            $ctx->drawString($c->label(), $fRank, Color::rgb($col), $x + 6.0, $y + 6.0);
            $ctx->drawString($c->label(), $fMid, Color::rgb($col), $x, $y + $h * 0.30, $w, DrawTextAlign::Center);
            $ctx->drawString($c->suit, $fSuit, Color::rgb($col), $x, $y + $h - 24.0, $w, DrawTextAlign::Center);
        }
    }

    private function drawBanner(WinDrawContext $ctx, float $W, float $y): void
    {
        $ps = $this->game->players[$this->human];
        $char = Character::byId($ps->characterId);
        $fc = $this->factionColor($ps->faction);
        $skill = $char->skill();
        $cost = $skill->cost;
        if ($cost === 'once') {
            $st = $ps->skill['usedOnce'] ? '已用' : '可用';
        } elseif (\str_starts_with($cost, 'charges:')) {
            $st = '充能 ' . $ps->skill['chargesLeft'];
        } else {
            $st = '霸气 ' . $ps->skill['haki'];
        }
        $f = new FontDescriptor(FONT, 13, TextWeight::Bold);
        $text = '你 · ' . $char->name . ' ｜ ' . $skill->name . '：' . $st;
        if ($this->autoPlay) {
            $text .= '  ｜ 🤖 托管中';
        }
        $ctx->drawString($text, $f, Color::rgb($fc), 16.0, $y);
    }

    private function drawLog(WinDrawContext $ctx, float $W, float $y): void
    {
        $f = new FontDescriptor(FONT, 9);
        $lines = \array_slice($this->gameLog, -4);
        // 左对齐、距左边缘 12px，mb_substr 按字符截断（不是字节！）
        $logX = 12.0;
        $i = 0;
        foreach ($lines as $line) {
            // ddz_utf8_substr 安全截断 UTF-8，避免从汉字中间劈开产生乱码
            $ctx->drawString(ddz_utf8_substr($line, 0, 28), $f, Color::rgb(0x94a3b8),
                $logX, $y + $i * 12.0);
            $i++;
        }
    }

    private function drawOver(WinDrawContext $ctx, float $W, float $H): void
    {
        // 深色半透明遮罩
        $ctx->fillRect(0.0, 0.0, $W, $H, Brush::color(Color::rgba(0.02, 0.05, 0.12, 0.85)));

        $win = \str_contains($this->message, '胜利');
        $fBig = new FontDescriptor(FONT, 44, TextWeight::Bold);
        $fSub = new FontDescriptor(FONT, 17);
        $fHint = new FontDescriptor(FONT, 14);
        $col = $win ? Color::rgb(0xfbbf24) : Color::rgb(0xf87171);

        // 垂直居中偏上一点
        $cy = $H * 0.38;
        // 水平居中：用 x=W*0.25, width=W*0.5 使对齐区域中心恰在 W*0.5
        // （fillRect 后 DrawTextAlign::Center 的 x=0 行为异常，改用非零宽度区域）
        $alignLeft = $W * 0.25;
        $alignWidth = $W * 0.5;

        // 主标题：胜利 / 战败 — 居中
        $ctx->drawString($win ? '胜 利' : '战 败', $fBig, $col,
            $alignLeft, $cy, $alignWidth, DrawTextAlign::Center);

        // 副标题：如 "🎉 胜利！（农民方获胜）"
        $ctx->drawString($this->message, $fSub, Color::rgb(0xe2e8f0),
            $alignLeft, $cy + 56.0, $alignWidth, DrawTextAlign::Center);

        // 提示文字
        $ctx->drawString('点击下方「再来一局」按钮开始新一局', $fHint,
            Color::rgb(0x94a3b8), $alignLeft, $cy + 92.0, $alignWidth, DrawTextAlign::Center);
    }

    /* -------------------------- select screen ------------------------ */

    private function drawSelectScreen(WinDrawContext $ctx, float $W, float $H): void
    {
        $this->title($ctx, $W);
        $fHead = new FontDescriptor(FONT, 20, TextWeight::Bold);
        $ctx->drawString('选择你的阵营与将领', $fHead, Color::rgb(0xe2e8f0), 0.0, 40.0, $W, DrawTextAlign::Center);

        $factions = [Faction::NAVY, Faction::WARLORD, Faction::EMPEROR];
        $colW = ($W - 64.0) / 3.0;
        $top = 80.0;
        $cardH = 150.0;
        $gapY = 14.0;
        foreach ($factions as $fi => $fac) {
            $fx = 16.0 + $fi * ($colW + 16.0);
            $fc = $this->factionColor($fac);
            $ctx->fillRoundedRect($fx, $top, $colW, 34.0, 8.0, Brush::rgb($fc));
            $ctx->drawString(Faction::name($fac), new FontDescriptor(FONT, 16, TextWeight::Bold),
                Color::rgb(0xffffff), $fx, $top + 7.0, $colW, DrawTextAlign::Center);
            $chars = Character::byFaction($fac);
            foreach ($chars as $ci => $ch) {
                $cy = $top + 44.0 + $ci * ($cardH + $gapY);
                $this->hit['select'][$ch->id] = [$fx, $cy, $colW, $cardH];
                $ctx->fillRoundedRect($fx, $cy, $colW, $cardH, 10.0,
                    Brush::color(Color::rgba(0.06, 0.11, 0.24, 0.9)));
                $ctx->strokeRoundedRect($fx, $cy, $colW, $cardH, 10.0, Brush::rgb($fc),
                    (new StrokeParams())->thickness(1.5));
                $ctx->fillRoundedRect($fx, $cy, 6.0, $cardH, 3.0, Brush::rgb($fc));

                $ctx->drawString($ch->name, new FontDescriptor(FONT, 15, TextWeight::Bold),
                    Color::rgb(0xf1f5f9), $fx + 14.0, $cy + 10.0);
                $ctx->drawString($ch->title, new FontDescriptor(FONT, 11),
                    Color::rgba(0.85, 0.9, 1.0, 0.8), $fx + 14.0, $cy + 30.0);
                $ctx->drawString('技能：' . $ch->skillName, new FontDescriptor(FONT, 12, TextWeight::Bold),
                    Color::rgb($fc), $fx + 14.0, $cy + 50.0);
                $desc = $this->wrap($ch->skillDesc, 22);
                $dy = $cy + 70.0;
                foreach ($desc as $line) {
                    $ctx->drawString($line, new FontDescriptor(FONT, 10), Color::rgb(0xcbd5e1),
                        $fx + 14.0, $dy);
                    $dy += 14.0;
                }
                $ctx->drawString('费用：' . $ch->skillCost, new FontDescriptor(FONT, 10),
                    Color::rgb(0x94a3b8), $fx + 14.0, $cy + $cardH - 16.0);
            }
        }
    }

    /** 简单按字符数折行（CJK 近似每行固定字数）。 */
    private function wrap(string $s, int $n): array
    {
        $out = [];
        $len = ddz_utf8_len($s);
        for ($i = 0; $i < $len; $i += $n) {
            $out[] = ddz_utf8_substr($s, $i, $n);
        }

        return $out;
    }
}
