<?php

class LandlordGame
{
    public string $phase = 'bidding';
    public array $scores = [500, 500, 500];
    public array $hands = [[], [], []];
    public array $bottom = [];
    public array $lastCards = [];
    public array $playedCards = [];
    public array $lastPlay;
    public int $lastPlayer = -1;
    public int $currentTurn = 0;
    public int $passCount = 0;
    public int $winner = -1;
    public int $landlord = -1;
    public int $bidTurn = 0;
    public int $bidStart = 0;
    public int $bidWinner = -1;
    public int $bidLevel = 0;
    public int $bidActions = 0;
    public int $bidPasses = 0;
    public int $multiplier = 1;
    public array $hasPlayed = [false, false, false];
    public bool $settled = false;
    public bool $robMode = false;
    public array $selected = [];
    public string $message = '';
    public int $lastAiTime = 0;
    public array $aiNames = [1 => '左侧AI', 2 => '右侧AI'];

    public function __construct()
    {
        $this->lastPlay = ['valid' => false, 'type' => 'none', 'main' => 0, 'len' => 0];
        $this->newGame();
    }

    public function newGame(): void
    {
        $this->randomizeAiNames();
        $deck = [];
        for ($i = 0; $i < 54; $i++) {
            $deck[] = $i;
        }
        shuffle($deck);

        $this->hands = [[], [], []];
        for ($i = 0; $i < 51; $i++) {
            $this->hands[$i % 3][] = $deck[$i];
        }
        $this->bottom = [$deck[51], $deck[52], $deck[53]];
        for ($i = 0; $i < 3; $i++) {
            sort_cards($this->hands[$i]);
        }
        sort_cards($this->bottom);

        $this->phase = 'bidding';
        $this->lastCards = [];
        $this->playedCards = [];
        $this->lastPlay = ['valid' => false, 'type' => 'none', 'main' => 0, 'len' => 0];
        $this->lastPlayer = -1;
        $this->currentTurn = -1;
        $this->passCount = 0;
        $this->winner = -1;
        $this->landlord = -1;
        $this->bidStart = random_int(0, 2);
        $this->bidTurn = $this->bidStart;
        $this->bidWinner = -1;
        $this->bidLevel = 0;
        $this->bidActions = 0;
        $this->bidPasses = 0;
        $this->multiplier = 1;
        $this->hasPlayed = [false, false, false];
        $this->settled = false;
        $this->robMode = false;
        $this->selected = [];
        $this->message = $this->playerName($this->bidTurn) . ' 选择是否叫地主。';
        $this->lastAiTime = win_get_tick_count();
    }

    public function isHumanActionTurn(): bool
    {
        if ($this->phase == 'bidding') {
            return $this->bidTurn == 0;
        }
        return $this->phase == 'playing' && $this->currentTurn == 0 && $this->winner < 0;
    }

    public function primaryActionLabel(): string
    {
        if ($this->phase == 'bidding') {
            return $this->robMode ? '抢地主' : '叫地主';
        }
        return '出牌';
    }

    public function secondaryActionLabel(): string
    {
        return $this->phase == 'bidding' ? '不叫/不抢' : '不出';
    }

    public function humanPrimaryAction(): void
    {
        if ($this->phase == 'bidding') {
            $this->bidAction(0, true);
        } else {
            $this->humanPlay();
        }
    }

    public function humanSecondaryAction(): void
    {
        if ($this->phase == 'bidding') {
            $this->bidAction(0, false);
        } else {
            $this->humanPass();
        }
    }

    public function toggleCardAt(int $index): void
    {
        if (!$this->isHumanActionTurn() || $this->phase != 'playing') {
            return;
        }
        if (!isset($this->hands[0][$index])) {
            return;
        }
        if (isset($this->selected[$index])) {
            unset($this->selected[$index]);
        } else {
            $this->selected[$index] = true;
        }
    }

    public function selectedCards(): array
    {
        $cards = [];
        foreach ($this->selected as $idx => $_) {
            if (isset($this->hands[0][$idx])) {
                $cards[] = $this->hands[0][$idx];
            }
        }
        sort_cards($cards);
        return $cards;
    }

    public function humanPlay(): void
    {
        if ($this->phase != 'playing' || $this->currentTurn != 0 || $this->winner >= 0) {
            return;
        }
        $cards = $this->selectedCards();
        $play = analyze_play($cards);
        if (!can_beat($play, $this->activeLastPlay())) {
            $this->message = '出牌无效，或牌型/点数压不过上一手。';
            return;
        }
        $this->commitPlay(0, $cards, $play);
    }

    public function humanPass(): void
    {
        if ($this->phase != 'playing' || $this->currentTurn != 0 || $this->winner >= 0) {
            return;
        }
        if (!$this->activeLastPlay()['valid']) {
            $this->message = '当前由你领出，必须出一手有效牌。';
            return;
        }
        $this->selected = [];
        $this->passPlay(0);
    }

    public function updateAi(): void
    {
        if ($this->winner >= 0) {
            return;
        }
        if (($this->phase == 'bidding' && $this->bidTurn == 0) || ($this->phase == 'playing' && $this->currentTurn == 0)) {
            return;
        }
        $now = win_get_tick_count();
        if ($now - $this->lastAiTime < 650) {
            return;
        }

        if ($this->phase == 'bidding') {
            $want = $this->shouldBidLandlord($this->bidTurn);
            $this->bidAction($this->bidTurn, $want);
        } else {
            $player = $this->currentTurn;
            $choice = $this->findAiPlay($player, $this->hands[$player], $this->activeLastPlay());
            if (count($choice) == 0) {
                $this->passPlay($player);
            } else {
                $this->commitPlay($player, $choice, analyze_play($choice));
            }
        }
        $this->lastAiTime = $now;
    }

    private function shouldBidLandlord(int $player): bool
    {
        $score = $this->evaluateLandlordHand($this->hands[$player]);
        $risk = $this->multiplierRiskPenalty();

        if (!$this->robMode) {
            return $score >= 50 + $risk;
        }

        $leaderAdvantage = 0;
        if ($this->bidWinner >= 0) {
            $leaderAdvantage = $this->evaluateLandlordHand($this->hands[$this->bidWinner]) - 55;
        }
        $threshold = 61 + $risk + $this->bidLevel * 5 + max(0, intdiv($leaderAdvantage, 6));
        return $score >= $threshold;
    }

    private function multiplierRiskPenalty(): int
    {
        if ($this->multiplier >= 8) {
            return 20;
        }
        if ($this->multiplier >= 4) {
            return 12;
        }
        if ($this->multiplier >= 2) {
            return 5;
        }
        return 0;
    }

    private function evaluateLandlordHand(array $hand): int
    {
        $counts = rank_counts($hand);
        $score = 0;

        if (isset($counts[16]) && isset($counts[17])) {
            $score += 34;
        } elseif (isset($counts[17])) {
            $score += 18;
        } elseif (isset($counts[16])) {
            $score += 12;
        }

        foreach ($counts as $rank => $count) {
            if ($rank == 15) {
                $score += $count * 7;
            } elseif ($rank == 14) {
                $score += $count * 5;
            } elseif ($rank == 13) {
                $score += $count * 3;
            } elseif ($rank >= 11) {
                $score += $count;
            }

            if ($count == 4) {
                $score += $rank >= 14 ? 26 : 18;
            } elseif ($count == 3) {
                $score += $rank >= 12 ? 10 : 6;
            } elseif ($count == 2 && $rank >= 12) {
                $score += 4;
            }
        }

        $score += $this->biddingShapeScore($counts);
        $score -= $this->looseCardPenalty($counts);
        return $score;
    }

    private function biddingShapeScore(array $counts): int
    {
        $score = 0;
        $singleRanks = [];
        $pairRanks = [];
        foreach ($counts as $rank => $count) {
            if ($rank >= 15) {
                continue;
            }
            if ($count >= 1) {
                $singleRanks[] = $rank;
            }
            if ($count >= 2) {
                $pairRanks[] = $rank;
            }
        }

        $score += $this->longestConsecutive($singleRanks) >= 5 ? 10 : 0;
        $score += $this->longestConsecutive($singleRanks) >= 7 ? 8 : 0;
        $score += $this->longestConsecutive($pairRanks) >= 3 ? 9 : 0;
        return $score;
    }

    private function looseCardPenalty(array $counts): int
    {
        $penalty = 0;
        foreach ($counts as $rank => $count) {
            if ($count == 1 && $rank <= 10) {
                $penalty += 3;
            } elseif ($count == 1 && $rank <= 12) {
                $penalty += 1;
            }
        }
        return min(24, $penalty);
    }

    private function longestConsecutive(array $ranks): int
    {
        if (count($ranks) == 0) {
            return 0;
        }
        sort($ranks);
        $best = 1;
        $run = 1;
        for ($i = 1; $i < count($ranks); $i++) {
            if ($ranks[$i] == $ranks[$i - 1] + 1) {
                $run++;
            } elseif ($ranks[$i] != $ranks[$i - 1]) {
                $run = 1;
            }
            if ($run > $best) {
                $best = $run;
            }
        }
        return $best;
    }

    private function bidAction(int $player, bool $accept): void
    {
        if ($this->phase != 'bidding' || $player != $this->bidTurn) {
            return;
        }

        if ($accept) {
            $isRob = $this->bidWinner >= 0;
            $this->bidWinner = $player;
            $this->bidLevel++;
            $this->bidPasses = 0;
            $this->robMode = true;
            if ($isRob) {
                $this->multiplier *= 2;
            }
            $verb = $isRob ? '抢地主' : '叫地主';
            $this->message = $this->playerName($player) . $verb . '。';
        } else {
            $this->message = $this->playerName($player) . '选择不叫/不抢。';
            if ($this->bidWinner >= 0) {
                $this->bidPasses++;
            } else {
                $this->bidActions++;
            }
        }

        if ($this->bidLevel >= 3 || ($this->bidWinner >= 0 && $this->bidPasses >= 2)) {
            $this->startPlaying($this->bidWinner);
            return;
        }
        if ($this->bidWinner < 0 && $this->bidActions >= 3) {
            $this->message = '三家都不叫地主，重新发牌。';
            $this->newGame();
            return;
        }

        $this->bidTurn = $this->nextPlayer($this->bidTurn);
        if ($this->bidWinner < 0) {
            $this->message .= ' 轮到' . $this->playerName($this->bidTurn) . '叫地主。';
        } else {
            $this->message .= ' 轮到' . $this->playerName($this->bidTurn) . '抢地主。';
        }
        $this->lastAiTime = win_get_tick_count();
    }

    private function startPlaying(int $landlord): void
    {
        $this->phase = 'playing';
        $this->landlord = $landlord;
        foreach ($this->bottom as $card) {
            $this->hands[$landlord][] = $card;
        }
        sort_cards($this->hands[$landlord]);

        $this->currentTurn = $landlord;
        $this->lastPlay = ['valid' => false, 'type' => 'none', 'main' => 0, 'len' => 0];
        $this->lastCards = [];
        $this->lastPlayer = -1;
        $this->passCount = 0;
        $this->selected = [];
        $this->message = $this->playerName($landlord) . '成为地主并先出牌。';
        $this->lastAiTime = win_get_tick_count();
    }

    private function activeLastPlay(): array
    {
        if ($this->lastPlayer == $this->currentTurn) {
            return ['valid' => false, 'type' => 'none', 'main' => 0, 'len' => 0];
        }
        return $this->lastPlay;
    }

    private function commitPlay(int $player, array $cards, array $play): void
    {
        remove_cards($this->hands[$player], $cards);
        $this->lastCards = $cards;
        foreach ($cards as $card) {
            $this->playedCards[] = $card;
        }
        $this->lastPlay = $play;
        $this->lastPlayer = $player;
        $this->passCount = 0;
        $this->selected = [];
        $this->hasPlayed[$player] = true;
        if ($play['type'] == 'bomb' || $play['type'] == 'rocket') {
            $this->multiplier *= 2;
        }
        $this->message = $this->playerName($player) . '打出' . play_name($play);

        if (count($this->hands[$player]) == 0) {
            $this->winner = $player;
            $summary = $this->settleRound($player);
            $this->message = $this->playerName($player) . '获胜。' . $summary . ' 点击“新一局”。';
            win_message_box(0, $this->message, '游戏结束', MB_OK);
            return;
        }
        $this->advancePlay();
    }

    private function passPlay(int $player): void
    {
        $this->passCount++;
        $this->message = $this->playerName($player) . '不出。';
        if ($this->passCount >= 2 && $this->lastPlayer >= 0) {
            $this->currentTurn = $this->lastPlayer;
            $this->lastPlay = ['valid' => false, 'type' => 'none', 'main' => 0, 'len' => 0];
            $this->lastCards = [];
            $this->passCount = 0;
            $this->message = $this->playerName($this->currentTurn) . '重新领出。';
            return;
        }
        $this->advancePlay();
    }

    private function advancePlay(): void
    {
        $this->currentTurn = $this->nextPlayer($this->currentTurn);
        if ($this->currentTurn != 0) {
            $this->lastAiTime = win_get_tick_count();
        }
    }

    private function nextPlayer(int $player): int
    {
        return ($player + 2) % 3;
    }

    public function playerName(int $player): string
    {
        $name = $player == 0 ? '你' : ($this->aiNames[$player] ?? 'AI');
        return $player == $this->landlord ? $name . '（地主）' : $name;
    }

    private function randomizeAiNames(): void
    {
        $names = ['阿明', '小北', '老周', '阿兰', '小雨', '大海', '阿峰', '小陈', '老赵', '阿杰'];
        shuffle($names);
        $this->aiNames = [1 => $names[0], 2 => $names[1]];
    }

    private function settleRound(int $winner): string
    {
        if ($this->settled) {
            return '';
        }
        $this->settled = true;

        if ($winner == $this->landlord && !$this->hasPlayed[($this->landlord + 1) % 3] && !$this->hasPlayed[($this->landlord + 2) % 3]) {
            $this->multiplier *= 2;
        }

        $points = $this->multiplier;
        if ($winner == $this->landlord) {
            for ($i = 0; $i < 3; $i++) {
                if ($i == $this->landlord) {
                    $this->scores[$i] += 2 * $points;
                } else {
                    $this->scores[$i] -= $points;
                }
            }
            return '地主胜，倍率x' . $this->multiplier . '，地主+' . (2 * $points) . '，农民各-' . $points . '。';
        }

        for ($i = 0; $i < 3; $i++) {
            if ($i == $this->landlord) {
                $this->scores[$i] -= 2 * $points;
            } else {
                $this->scores[$i] += $points;
            }
        }
        return '农民胜，倍率x' . $this->multiplier . '，地主-' . (2 * $points) . '，农民各+' . $points . '。';
    }

    private function findAiPlay(int $player, array $hand, array $target): array
    {
        if (!$target['valid']) {
            return $this->chooseAiLead($player, $hand);
        }

        $coverAlly = $this->lastPlayer >= 0
            && $this->isSameSide($player, $this->lastPlayer)
            && $this->shouldOvertakeAlly($player, $target);

        if ($this->lastPlayer >= 0 && $this->isSameSide($player, $this->lastPlayer) && !$coverAlly) {
            return [];
        }

        $candidates = $this->buildSimpleCandidates($hand, $target);
        $next = $this->nextPlayer($player);
        $nextEnemyDanger = !$this->isSameSide($player, $next) && count($this->hands[$next]) <= 4;
        $lastEnemyDanger = $this->lastPlayer >= 0 && !$this->isSameSide($player, $this->lastPlayer) && count($this->hands[$this->lastPlayer]) <= 2;
        $mustFight = $lastEnemyDanger || $nextEnemyDanger || count($hand) <= 5 || $coverAlly;

        $winning = $this->findWinningCandidate($hand, $candidates, $target);
        if (count($winning) > 0 && ($mustFight || count($hand) <= 8)) {
            return $winning;
        }

        $this->sortCandidatesForContext($candidates, $player, $hand, true, $mustFight || $coverAlly);
        $best = [];
        $bestScore = -100000;
        foreach ($candidates as $cards) {
            $play = analyze_play($cards);
            if (can_beat($play, $target)) {
                if ($coverAlly && !$nextEnemyDanger && $this->isPowerPlay($play)) {
                    continue;
                }
                if (!$mustFight && $this->isPowerPlay($play)) {
                    continue;
                }
                $score = $this->candidateScore($player, $hand, $cards, true, $mustFight || $coverAlly);
                if ($score > $bestScore) {
                    $best = $cards;
                    $bestScore = $score;
                }
            }
        }
        if (count($best) > 0) {
            return $best;
        }

        if ($coverAlly && !$nextEnemyDanger) {
            return [];
        }

        if (!$mustFight) {
            return [];
        }

        foreach ($candidates as $cards) {
            $play = analyze_play($cards);
            if (can_beat($play, $target)) {
                return $cards;
            }
        }
        return [];
    }

    private function shouldOvertakeAlly(int $player, array $target): bool
    {
        $next = $this->nextPlayer($player);
        if ($this->isSameSide($player, $next)) {
            return false;
        }

        $nextCards = count($this->hands[$next]);
        if ($nextCards <= 3) {
            return true;
        }

        if ($target['type'] == 'single') {
            return $target['main'] <= 11;
        }
        if ($target['type'] == 'pair') {
            return $target['main'] <= 10;
        }
        if ($target['type'] == 'triple' || $target['type'] == 'triple_single' || $target['type'] == 'triple_pair') {
            return $target['main'] <= 9;
        }
        if ($target['type'] == 'straight' || $target['type'] == 'pair_sequence') {
            return $target['main'] <= 10;
        }
        return false;
    }

    private function chooseAiLead(int $player, array $hand): array
    {
        $byRank = $this->cardsByRank($hand);
        $next = $this->nextPlayer($player);
        $nextIsEnemy = !$this->isSameSide($player, $next);
        $nextCards = count($this->hands[$next]);
        $enemyMinCards = $this->minEnemyCards($player);

        if ($player == $this->landlord && $enemyMinCards <= 2) {
            $block = $this->chooseLandlordBlockLead($hand, $byRank, $enemyMinCards);
            if (count($block) > 0) {
                return $block;
            }
        }

        if (count($hand) <= 2 && analyze_play($hand)['valid']) {
            return $hand;
        }

        if (!$nextIsEnemy && $nextCards <= 2) {
            $feed = $this->chooseAllyFeedLead($hand, $byRank, $nextCards);
            if (count($feed) > 0) {
                return $feed;
            }
        }

        $blockNext = $nextIsEnemy && count($this->hands[$next]) <= 2;

        if ($blockNext) {
            $block = $this->chooseBlockingLead($hand, $byRank);
            if (count($block) > 0) {
                return $block;
            }
        }

        $leadCandidates = $this->buildLeadCandidates($hand, $byRank);
        $winning = $this->findWinningCandidate($hand, $leadCandidates, ['valid' => false, 'type' => 'none', 'main' => 0, 'len' => 0]);
        if (count($winning) > 0) {
            return $winning;
        }
        if (count($leadCandidates) > 0) {
            $this->sortCandidatesForContext($leadCandidates, $player, $hand, false, $blockNext);
            return $leadCandidates[0];
        }

        foreach ($byRank as $rank => $cards) {
            if ($rank < 15 && count($cards) >= 3) {
                foreach ($hand as $kicker) {
                    if (card_rank($kicker) != $rank) {
                        return [$cards[0], $cards[1], $cards[2], $kicker];
                    }
                }
                return [$cards[0], $cards[1], $cards[2]];
            }
        }
        $straight = $this->firstSequence($byRank, 5, 1);
        if (count($straight) > 0) {
            return $straight;
        }
        foreach ($byRank as $rank => $cards) {
            if ($rank < 15 && count($cards) >= 2) {
                return [$cards[0], $cards[1]];
            }
        }
        return [$hand[0]];
    }

    private function chooseLandlordBlockLead(array $hand, array $byRank, int $enemyCards): array
    {
        if ($enemyCards == 1) {
            foreach ($byRank as $rank => $cards) {
                if ($rank < 15 && count($cards) >= 2) {
                    return [$cards[0], $cards[1]];
                }
            }

            $straight = $this->firstSequence($byRank, 5, 1);
            if (count($straight) > 0) {
                return $straight;
            }

            foreach ($byRank as $rank => $cards) {
                if ($rank < 15 && count($cards) >= 3) {
                    return [$cards[0], $cards[1], $cards[2]];
                }
            }
            return [];
        }

        foreach ($hand as $card) {
            if (card_rank($card) < 15) {
                return [$card];
            }
        }
        return [$hand[0]];
    }

    private function chooseAllyFeedLead(array $hand, array $byRank, int $allyCards): array
    {
        if ($allyCards == 2) {
            foreach ($byRank as $rank => $cards) {
                if ($rank < 15 && count($cards) >= 2) {
                    return [$cards[0], $cards[1]];
                }
            }
        }

        foreach ($hand as $card) {
            if (card_rank($card) < 15) {
                return [$card];
            }
        }
        return [$hand[0]];
    }

    private function chooseBlockingLead(array $hand, array $byRank): array
    {
        foreach ($byRank as $rank => $cards) {
            if ($rank >= 11 && $rank < 15 && count($cards) >= 2) {
                return [$cards[0], $cards[1]];
            }
        }
        foreach ($hand as $card) {
            $rank = card_rank($card);
            if ($rank >= 12 && $rank < 15) {
                return [$card];
            }
        }
        foreach ($byRank as $rank => $cards) {
            if ($rank < 15 && count($cards) >= 3) {
                return [$cards[0], $cards[1], $cards[2]];
            }
        }
        return [];
    }

    private function sortCandidates(array &$candidates): void
    {
        usort($candidates, function (array $a, array $b): int {
            $pa = analyze_play($a);
            $pb = analyze_play($b);
            $ca = $this->playCost($pa, count($a));
            $cb = $this->playCost($pb, count($b));
            if ($ca == $cb) {
                return $pa['main'] <=> $pb['main'];
            }
            return $ca <=> $cb;
        });
    }

    private function sortCandidatesForContext(array &$candidates, int $player, array $hand, bool $following, bool $urgent): void
    {
        usort($candidates, function (array $a, array $b) use ($player, $hand, $following, $urgent): int {
            $sa = $this->candidateScore($player, $hand, $a, $following, $urgent);
            $sb = $this->candidateScore($player, $hand, $b, $following, $urgent);
            if ($sa == $sb) {
                $pa = analyze_play($a);
                $pb = analyze_play($b);
                return $pa['main'] <=> $pb['main'];
            }
            return $sb <=> $sa;
        });
    }

    private function candidateScore(int $player, array $hand, array $cards, bool $following, bool $urgent): int
    {
        $play = analyze_play($cards);
        $remaining = count($hand) - count($cards);
        $remainingHand = $this->remainingAfter($hand, $cards);
        $score = count($cards) * 22 - $play['main'] * 2;
        $score += ($this->estimateTurns($hand) - $this->estimateTurns($remainingHand)) * 45;
        $score += $this->estimateWinChance($player, $remainingHand) * 3;

        if ($remaining == 0) {
            $score += 10000;
        } elseif ($remaining <= 2) {
            $score += 180;
        }

        if ($player == $this->landlord && $remaining > 0) {
            $enemyMin = $this->minEnemyCards($player);
            if ($enemyMin == 1 && $play['type'] == 'single') {
                $score -= 1200;
            } elseif ($enemyMin == 2 && $play['type'] == 'pair') {
                $score -= 1200;
            }
        }

        if (!$following && $this->hasSingleControl($remainingHand)) {
            if ($play['type'] == 'single' && $play['main'] <= 10) {
                $score += 150;
            } elseif ($play['type'] == 'pair' && $play['main'] <= 10) {
                $score += 90;
            }
        }

        if ($play['type'] == 'straight' || $play['type'] == 'pair_sequence') {
            $score += 40;
        } elseif ($play['type'] == 'airplane') {
            $score += 70;
        } elseif ($play['type'] == 'triple_single' || $play['type'] == 'triple_pair') {
            $score += 45;
        } elseif ($play['type'] == 'four_two_single' || $play['type'] == 'four_two_pair') {
            $score += 55;
        }

        $next = $this->nextPlayer($player);
        if (!$this->isSameSide($player, $next)) {
            $nextCards = count($this->hands[$next]);
            if ($nextCards == 1 && $play['type'] != 'single') {
                $score += 180;
            } elseif ($nextCards == 2 && $play['type'] == 'single') {
                $score += 140;
            }
        } elseif (!$following && count($this->hands[$next]) <= 2) {
            if (count($this->hands[$next]) == 1 && $play['type'] == 'single') {
                $score += 180;
            } elseif (count($this->hands[$next]) == 2 && $play['type'] == 'pair') {
                $score += 180;
            }
        }

        if ($play['type'] == 'bomb' || $play['type'] == 'rocket') {
            if ($remaining <= 2 || $urgent || $this->estimateWinChance($player, $remainingHand) >= 75) {
                $score += 260 + $this->multiplier * 10;
            } else {
                $score -= 520;
            }
        } elseif ($play['main'] >= 15 && !$urgent && $remaining > 4) {
            $score -= $this->hasSingleControl($remainingHand) ? 70 : 160;
        }

        return $score;
    }

    private function remainingAfter(array $hand, array $cards): array
    {
        $remaining = $hand;
        remove_cards($remaining, $cards);
        return $remaining;
    }

    private function estimateWinChance(int $player, array $remainingHand): int
    {
        if (count($remainingHand) == 0) {
            return 100;
        }

        $turns = $this->estimateTurns($remainingHand);
        $chance = 45;
        $chance += max(0, 6 - $turns) * 8;
        $chance += $this->hasSingleControl($remainingHand) ? 20 : 0;
        $chance += $this->hasBombInHand($remainingHand) ? 12 : 0;

        $enemyMin = 30;
        $allyMin = 30;
        for ($i = 0; $i < 3; $i++) {
            if ($i == $player) {
                continue;
            }
            if ($this->isSameSide($player, $i)) {
                $allyMin = min($allyMin, count($this->hands[$i]));
            } else {
                $enemyMin = min($enemyMin, count($this->hands[$i]));
            }
        }

        if ($enemyMin <= 1) {
            $chance -= 30;
        } elseif ($enemyMin <= 3) {
            $chance -= 16;
        }
        if ($allyMin <= 2 && $player != $this->landlord) {
            $chance += 12;
        }
        if ($player == $this->landlord && count($remainingHand) <= 4) {
            $chance += 8;
        }

        return max(5, min(95, $chance));
    }

    private function hasBombInHand(array $hand): bool
    {
        $counts = rank_counts($hand);
        foreach ($counts as $count) {
            if ($count == 4) {
                return true;
            }
        }
        return isset($counts[16]) && isset($counts[17]);
    }

    private function hasSingleControl(array $hand): bool
    {
        if (in_array(53, $hand, true)) {
            return true;
        }
        if (in_array(52, $hand, true) && $this->isCardPlayed(53)) {
            return true;
        }

        for ($rank = 15; $rank >= 3; $rank--) {
            if ($this->rankCount($hand, $rank) == 0) {
                continue;
            }
            return $this->allHigherRanksAccounted($rank, $hand);
        }
        return false;
    }

    private function allHigherRanksAccounted(int $rank, array $hand): bool
    {
        for ($r = 17; $r > $rank; $r--) {
            if ($this->rankCount($this->playedCards, $r) + $this->rankCount($hand, $r) < $this->totalRankCount($r)) {
                return false;
            }
        }
        return true;
    }

    private function isCardPlayed(int $card): bool
    {
        return in_array($card, $this->playedCards, true);
    }

    private function rankCount(array $cards, int $rank): int
    {
        $count = 0;
        foreach ($cards as $card) {
            if (card_rank($card) == $rank) {
                $count++;
            }
        }
        return $count;
    }

    private function totalRankCount(int $rank): int
    {
        return $rank >= 16 ? 1 : 4;
    }

    private function estimateTurns(array $cards): int
    {
        if (count($cards) == 0) {
            return 0;
        }
        if (analyze_play($cards)['valid']) {
            return 1;
        }
        $counts = rank_counts($cards);
        $turns = 0;
        foreach ($counts as $count) {
            if ($count >= 3) {
                $turns++;
            } elseif ($count == 2) {
                $turns++;
            } else {
                $turns++;
            }
        }
        return $turns;
    }

    private function playCost(array $play, int $cardCount): int
    {
        $cost = $play['main'] * 10 + $cardCount;
        if ($play['type'] == 'bomb') {
            $cost += 600;
        } elseif ($play['type'] == 'rocket') {
            $cost += 900;
        } elseif ($play['main'] >= 15) {
            $cost += 160;
        }
        return $cost;
    }

    private function isPowerPlay(array $play): bool
    {
        return $play['type'] == 'bomb' || $play['type'] == 'rocket' || $play['main'] >= 15;
    }

    private function isSameSide(int $a, int $b): bool
    {
        if ($a == $this->landlord || $b == $this->landlord) {
            return $a == $b;
        }
        return true;
    }

    private function minEnemyCards(int $player): int
    {
        $min = 99;
        for ($i = 0; $i < 3; $i++) {
            if ($i != $player && !$this->isSameSide($player, $i)) {
                $min = min($min, count($this->hands[$i]));
            }
        }
        return $min == 99 ? 99 : $min;
    }

    private function findWinningCandidate(array $hand, array $candidates, array $target): array
    {
        foreach ($candidates as $cards) {
            if (count($cards) == count($hand)) {
                $play = analyze_play($cards);
                if (can_beat($play, $target)) {
                    return $cards;
                }
            }
        }
        return [];
    }

    private function buildLeadCandidates(array $hand, array $byRank): array
    {
        $out = [];
        foreach ($hand as $card) {
            $out[] = [$card];
        }
        foreach ($byRank as $rank => $cards) {
            if (count($cards) >= 2) {
                $out[] = [$cards[0], $cards[1]];
            }
            if (count($cards) >= 3) {
                $out[] = [$cards[0], $cards[1], $cards[2]];
                foreach ($hand as $kicker) {
                    if (card_rank($kicker) != $rank) {
                        $out[] = [$cards[0], $cards[1], $cards[2], $kicker];
                        break;
                    }
                }
                foreach ($byRank as $pairRank => $pairCards) {
                    if ($pairRank != $rank && count($pairCards) >= 2) {
                        $out[] = [$cards[0], $cards[1], $cards[2], $pairCards[0], $pairCards[1]];
                        break;
                    }
                }
            }
        }

        for ($len = 5; $len <= 12; $len++) {
            $out = array_merge($out, $this->sequenceCandidates($byRank, $len, 1));
        }
        for ($len = 3; $len <= 8; $len++) {
            $out = array_merge($out, $this->sequenceCandidates($byRank, $len, 2));
        }
        for ($len = 2; $len <= 5; $len++) {
            $out = array_merge($out, $this->sequenceCandidates($byRank, $len, 3));
        }
        $out = array_merge($out, $this->fourTwoCandidates($hand, $byRank, false));
        $out = array_merge($out, $this->fourTwoCandidates($hand, $byRank, true));

        foreach ($byRank as $cards) {
            if (count($cards) == 4) {
                $out[] = [$cards[0], $cards[1], $cards[2], $cards[3]];
            }
        }
        if (isset($byRank[16]) && isset($byRank[17])) {
            $out[] = [$byRank[16][0], $byRank[17][0]];
        }
        return $out;
    }

    private function buildSimpleCandidates(array $hand, array $target): array
    {
        $byRank = $this->cardsByRank($hand);

        $out = [];
        $type = $target['type'];
        if ($type == 'single') {
            foreach ($hand as $card) {
                $out[] = [$card];
            }
        } elseif ($type == 'pair') {
            foreach ($byRank as $cards) {
                if (count($cards) >= 2) {
                    $out[] = [$cards[0], $cards[1]];
                }
            }
        } elseif ($type == 'triple' || $type == 'triple_single' || $type == 'triple_pair') {
            foreach ($byRank as $rank => $cards) {
                if (count($cards) < 3) {
                    continue;
                }
                if ($type == 'triple') {
                    $out[] = [$cards[0], $cards[1], $cards[2]];
                } elseif ($type == 'triple_single') {
                    foreach ($hand as $kicker) {
                        if (card_rank($kicker) != $rank) {
                            $out[] = [$cards[0], $cards[1], $cards[2], $kicker];
                            break;
                        }
                    }
                } else {
                    foreach ($byRank as $pairRank => $pairCards) {
                        if ($pairRank != $rank && count($pairCards) >= 2) {
                            $out[] = [$cards[0], $cards[1], $cards[2], $pairCards[0], $pairCards[1]];
                            break;
                        }
                    }
                }
            }
        } elseif ($type == 'straight') {
            $out = array_merge($out, $this->sequenceCandidates($byRank, $target['len'], 1));
        } elseif ($type == 'pair_sequence') {
            $out = array_merge($out, $this->sequenceCandidates($byRank, intdiv($target['len'], 2), 2));
        } elseif ($type == 'airplane') {
            $out = array_merge($out, $this->sequenceCandidates($byRank, intdiv($target['len'], 3), 3));
        } elseif ($type == 'four_two_single' || $type == 'four_two_pair') {
            $out = array_merge($out, $this->fourTwoCandidates($hand, $byRank, $type == 'four_two_pair'));
        }

        foreach ($byRank as $cards) {
            if (count($cards) == 4) {
                $out[] = [$cards[0], $cards[1], $cards[2], $cards[3]];
            }
        }
        if (isset($byRank[16]) && isset($byRank[17])) {
            $out[] = [$byRank[16][0], $byRank[17][0]];
        }
        return $out;
    }

    private function fourTwoCandidates(array $hand, array $byRank, bool $withPairs): array
    {
        $out = [];
        foreach ($byRank as $rank => $cards) {
            if (count($cards) < 4) {
                continue;
            }
            $candidate = [$cards[0], $cards[1], $cards[2], $cards[3]];
            if ($withPairs) {
                $pairs = [];
                foreach ($byRank as $pairRank => $pairCards) {
                    if ($pairRank != $rank && count($pairCards) >= 2) {
                        $pairs[] = [$pairCards[0], $pairCards[1]];
                    }
                }
                if (count($pairs) >= 2) {
                    $out[] = array_merge($candidate, $pairs[0], $pairs[1]);
                }
            } else {
                $kickers = [];
                foreach ($hand as $card) {
                    if (card_rank($card) != $rank) {
                        $kickers[] = $card;
                    }
                }
                if (count($kickers) >= 2) {
                    $out[] = array_merge($candidate, [$kickers[0], $kickers[1]]);
                }
            }
        }
        return $out;
    }

    private function cardsByRank(array $hand): array
    {
        $byRank = [];
        foreach ($hand as $card) {
            $rank = card_rank($card);
            if (!isset($byRank[$rank])) {
                $byRank[$rank] = [];
            }
            $byRank[$rank][] = $card;
        }
        ksort($byRank);
        return $byRank;
    }

    private function firstSequence(array $byRank, int $needRanks, int $cardsPerRank): array
    {
        $candidates = $this->sequenceCandidates($byRank, $needRanks, $cardsPerRank);
        return count($candidates) > 0 ? $candidates[0] : [];
    }

    private function sequenceCandidates(array $byRank, int $needRanks, int $cardsPerRank): array
    {
        $ranks = [];
        foreach ($byRank as $rank => $cards) {
            if ($rank < 15 && count($cards) >= $cardsPerRank) {
                $ranks[] = $rank;
            }
        }

        $out = [];
        for ($i = 0; $i <= count($ranks) - $needRanks; $i++) {
            $slice = array_slice($ranks, $i, $needRanks);
            if (!is_consecutive($slice)) {
                continue;
            }
            $cards = [];
            foreach ($slice as $rank) {
                for ($j = 0; $j < $cardsPerRank; $j++) {
                    $cards[] = $byRank[$rank][$j];
                }
            }
            $out[] = $cards;
        }
        return $out;
    }
}

class LandlordRenderer
{
    private int $hWnd;

    public function __construct(int $hWnd)
    {
        $this->hWnd = $hWnd;
    }

    public function render(LandlordGame $game): void
    {
        $hdc = win_begin_paint($this->hWnd);
        $this->drawTable($hdc);
        $this->drawTopBar($hdc, $game);
        $this->drawBottom($hdc, $game);
        $this->drawPlayer($hdc, $game, 1, 72, 205, false);
        $this->drawPlayer($hdc, $game, 2, WINDOW_WIDTH - 144, 205, true);
        $this->drawCenterStatus($hdc, $game);
        $this->drawLastPlay($hdc, $game);
        $this->drawButtons($hdc, $game);
        $this->drawHand($hdc, $game);
        $this->drawBottomScoreBar($hdc, $game);
        win_end_paint($this->hWnd, $hdc);
    }

    private function drawTable(int $hdc): void
    {
        for ($y = 0; $y < WINDOW_HEIGHT; $y += 8) {
            $t = intdiv($y * 90, WINDOW_HEIGHT);
            win_fill_rect($hdc, 0, $y, WINDOW_WIDTH, 8, rgb(20 + intdiv($t, 3), 65 + intdiv($t, 2), 125 + $t));
        }
        win_fill_ellipse($hdc, 230, 95, 720, 430, rgb(45, 115, 186));
        win_fill_ellipse($hdc, 315, 135, 550, 320, rgb(56, 132, 205));
        win_fill_rect($hdc, 0, 662, WINDOW_WIDTH, 98, rgb(40, 55, 86));
        win_fill_rect($hdc, 0, 690, WINDOW_WIDTH, 70, rgb(28, 38, 62));
        win_draw_text($hdc, 505, 176, '斗地主', 54, rgb(37, 93, 158), 1);
        win_draw_text($hdc, 560, 238, '单机', 19, rgb(35, 86, 145), 1);
    }

    private function drawTopBar(int $hdc, LandlordGame $game): void
    {
        win_fill_rect($hdc, 0, 0, WINDOW_WIDTH, 64, rgb(20, 46, 92));
        win_draw_text($hdc, 28, 18, '退出', 18, rgb(242, 245, 250), 1);
        win_draw_text($hdc, 94, 18, '记牌器', 18, rgb(242, 245, 250), 1);
        win_draw_text($hdc, 840, 16, '托管', 18, rgb(242, 245, 250), 1);
        win_draw_text($hdc, 910, 16, '菜单', 18, rgb(242, 245, 250), 1);
        win_draw_text($hdc, 995, 16, '分数 ' . $game->scores[0] . ' / ' . $game->scores[1] . ' / ' . $game->scores[2], 17, rgb(245, 224, 130), 1);
        win_draw_text($hdc, 1088, 38, '倍 x' . $game->multiplier, 17, rgb(255, 238, 118), 1);
    }

    private function drawCenterStatus(int $hdc, LandlordGame $game): void
    {
        if ($game->phase == 'bidding') {
            $title = '叫地主阶段';
            $sub = $game->bidLevel == 0 ? '等待玩家叫地主' : '地主候选：' . $game->playerName($game->bidWinner) . '，抢地主次数：' . max(0, $game->bidLevel - 1);
            $turn = '轮到：' . $game->playerName($game->bidTurn);
        } else {
            $title = '出牌阶段';
            $sub = $game->lastPlay['valid'] ? '上一手：' . $game->playerName($game->lastPlayer) . ' - ' . play_name($game->lastPlay) : '无人压牌，可任意领出';
            $turn = '当前：' . $game->playerName($game->currentTurn);
        }

        win_draw_text($hdc, 492, 118, $title, 25, rgb(248, 240, 190), 1);
        win_draw_text($hdc, 448, 154, $turn, 20, rgb(255, 236, 122), 1);
        win_draw_text($hdc, 410, 184, $sub, 17, rgb(218, 234, 244), 0);
        win_draw_text($hdc, 348, 470, $game->message, 18, rgb(248, 248, 235), 1);
    }

    private function drawPlayer(int $hdc, LandlordGame $game, int $player, int $x, int $y, bool $right): void
    {
        $active = ($game->phase == 'bidding' && $game->bidTurn == $player) || ($game->phase == 'playing' && $game->currentTurn == $player);
        $avatarColor = $player == 1 ? rgb(219, 82, 55) : rgb(236, 135, 46);
        $ring = $active ? rgb(255, 226, 83) : rgb(215, 232, 245);
        win_fill_ellipse($hdc, $x - 8, $y - 8, 76, 76, $ring);
        win_fill_ellipse($hdc, $x, $y, 60, 60, $avatarColor);
        win_draw_text($hdc, $x + 16, $y + 15, 'AI', 22, rgb(255, 245, 225), 1);

        $nameX = $right ? $x - 54 : $x - 8;
        win_fill_rect($hdc, $nameX, $y + 72, 116, 28, rgb(35, 92, 145));
        win_draw_text($hdc, $nameX + 15, $y + 76, $game->playerName($player), 16, rgb(240, 248, 255), 1);
        win_fill_rect($hdc, $nameX + 18, $y + 106, 84, 28, rgb(35, 54, 94));
        win_draw_text($hdc, $nameX + 30, $y + 110, '分 ' . $game->scores[$player], 16, rgb(255, 225, 86), 1);

        $cardX = $right ? $x - 110 : $x + 76;
        $shown = min(8, count($game->hands[$player]));
        for ($i = 0; $i < $shown; $i++) {
            $this->drawMiniCardBack($hdc, $cardX + ($right ? -$i * 14 : $i * 14), $y + 85);
        }
        win_fill_rect($hdc, $cardX + ($right ? -32 : 58), $y + 114, 34, 32, rgb(52, 92, 152));
        win_draw_text($hdc, $cardX + ($right ? -24 : 66), $y + 118, (string)count($game->hands[$player]), 19, rgb(245, 245, 245), 1);
    }

    private function drawBottom(int $hdc, LandlordGame $game): void
    {
        $x = 522;
        for ($i = 0; $i < count($game->bottom); $i++) {
            if ($game->phase == 'bidding') {
                $this->drawSmallCardBack($hdc, $x + $i * 42, 30);
            } else {
                $this->drawSmallCard($hdc, $x + $i * 42, 30, $game->bottom[$i]);
            }
        }
    }

    private function drawLastPlay(int $hdc, LandlordGame $game): void
    {
        if ($game->phase != 'playing' || count($game->lastCards) == 0) {
            return;
        }
        $gap = count($game->lastCards) > 8 ? 34 : 46;
        $total = CARD_W + max(0, count($game->lastCards) - 1) * $gap;
        $x = intdiv(WINDOW_WIDTH - $total, 2);
        for ($i = 0; $i < count($game->lastCards); $i++) {
            $this->drawCard($hdc, $x + $i * $gap, 300, $game->lastCards[$i], false, false);
        }
    }

    private function drawButtons(int $hdc, LandlordGame $game): void
    {
        $enabled = $game->isHumanActionTurn();
        $this->drawButton($hdc, BTN_PLAY_X, BTN_Y, $game->primaryActionLabel(), $enabled, true);
        $this->drawButton($hdc, BTN_PASS_X, BTN_Y, $game->secondaryActionLabel(), $enabled, false);
        $this->drawButton($hdc, BTN_NEW_X, BTN_Y, '新一局', true, false);
    }

    private function drawButton(int $hdc, int $x, int $y, string $label, bool $enabled, bool $primary): void
    {
        $fill = !$enabled ? rgb(87, 113, 145) : ($primary ? rgb(244, 190, 58) : rgb(245, 246, 238));
        $text = !$enabled ? rgb(185, 199, 212) : rgb(35, 45, 54);
        win_fill_rect($hdc, $x + 3, $y + 4, BTN_W, BTN_H, rgb(25, 56, 92));
        win_fill_rect($hdc, $x, $y, BTN_W, BTN_H, $fill);
        $this->drawRect($hdc, $x, $y, BTN_W, BTN_H, rgb(240, 224, 150));
        win_draw_text($hdc, $x + 28, $y + 11, $label, 18, $text, 1);
    }

    private function drawHand(int $hdc, LandlordGame $game): void
    {
        $hand = $game->hands[0];
        $count = count($hand);
        $gap = $this->handGap($count);
        $startX = $this->handStartX($count, $gap);

        for ($i = 0; $i < $count; $i++) {
            $selected = isset($game->selected[$i]);
            $focused = $game->phase == 'playing' && $game->currentTurn == 0 && $selected;
            $y = $selected ? 574 : 604;
            $this->drawCard($hdc, $startX + $i * $gap, $y, $hand[$i], $selected, $focused);
        }
    }

    private function drawBottomScoreBar(int $hdc, LandlordGame $game): void
    {
        win_fill_rect($hdc, 0, 704, WINDOW_WIDTH, 56, rgb(32, 43, 70));
        win_fill_ellipse($hdc, 58, 684, 64, 64, rgb(225, 238, 246));
        win_fill_ellipse($hdc, 64, 690, 52, 52, rgb(79, 128, 160));
        win_fill_rect($hdc, 135, 712, 190, 34, rgb(64, 60, 92));
        win_draw_text($hdc, 158, 718, '玩家：你' . ($game->landlord == 0 ? '（地主）' : ''), 18, rgb(245, 245, 245), 1);
        win_fill_rect($hdc, 360, 712, 180, 34, rgb(65, 72, 112));
        win_draw_text($hdc, 386, 718, '当前分数：' . $game->scores[0], 19, rgb(255, 226, 82), 1);
        win_fill_rect($hdc, 575, 712, 145, 34, rgb(38, 86, 104));
        win_draw_text($hdc, 600, 718, '手牌：' . count($game->hands[0]) . '张', 18, rgb(240, 246, 248), 1);
        win_fill_rect($hdc, 930, 712, 170, 34, rgb(45, 75, 118));
        win_draw_text($hdc, 958, 718, '当前倍率：x' . $game->multiplier, 19, rgb(255, 226, 82), 1);
    }

    public function hitTestHand(LandlordGame $game, int $x, int $y): int
    {
        $count = count($game->hands[0]);
        if ($count == 0) {
            return -1;
        }
        $gap = $this->handGap($count);
        $startX = $this->handStartX($count, $gap);
        for ($i = $count - 1; $i >= 0; $i--) {
            $selected = isset($game->selected[$i]);
            $cy = $selected ? 574 : 604;
            if (in_rect($x, $y, $startX + $i * $gap, $cy, CARD_W, CARD_H)) {
                return $i;
            }
        }
        return -1;
    }

    private function handGap(int $count): int
    {
        return $count > 18 ? 31 : CARD_GAP;
    }

    private function handStartX(int $count, int $gap): int
    {
        $total = CARD_W + max(0, $count - 1) * $gap;
        return max(26, intdiv(WINDOW_WIDTH - $total, 2));
    }

    private function drawMiniCardBack(int $hdc, int $x, int $y): void
    {
        win_fill_rect($hdc, $x, $y, 26, 38, rgb(46, 86, 164));
        $this->drawRect($hdc, $x, $y, 26, 38, rgb(225, 235, 245));
    }

    private function drawSmallCardBack(int $hdc, int $x, int $y): void
    {
        win_fill_rect($hdc, $x, $y, 34, 48, rgb(46, 86, 164));
        $this->drawRect($hdc, $x, $y, 34, 48, rgb(225, 235, 245));
        win_fill_rect($hdc, $x + 6, $y + 6, 22, 36, rgb(65, 110, 190));
    }

    private function drawSmallCard(int $hdc, int $x, int $y, int $card): void
    {
        win_fill_rect($hdc, $x, $y, 34, 48, rgb(252, 252, 244));
        $this->drawRect($hdc, $x, $y, 34, 48, rgb(45, 56, 66));
        $rank = card_rank($card);
        $red = ($rank == 17) || ($rank < 16 && (card_suit($card) == 1 || card_suit($card) == 3));
        $color = $red ? rgb(190, 25, 38) : rgb(25, 32, 34);
        if ($rank >= 16) {
            win_draw_text($hdc, $x + 4, $y + 12, 'J', 22, $color, 1);
        } else {
            win_draw_text($hdc, $x + 4, $y + 1, rank_label($rank), 22, $color, 1);
            win_draw_text($hdc, $x + 6, $y + 23, card_suit_label($card), 16, $color, 1);
        }
    }

    private function drawCard(int $hdc, int $x, int $y, int $card, bool $selected, bool $focused): void
    {
        $rank = card_rank($card);
        $red = ($rank == 17) || ($rank < 16 && (card_suit($card) == 1 || card_suit($card) == 3));
        $fill = $selected ? rgb(255, 247, 196) : rgb(252, 252, 244);
        $border = $focused ? rgb(255, 210, 70) : rgb(55, 70, 62);
        win_fill_rect($hdc, $x + 3, $y + 4, CARD_W, CARD_H, rgb(45, 54, 70));
        win_fill_rect($hdc, $x, $y, CARD_W, CARD_H, $fill);
        $this->drawRect($hdc, $x, $y, CARD_W, CARD_H, $border);
        if ($focused) {
            $this->drawRect($hdc, $x - 2, $y - 2, CARD_W + 4, CARD_H + 4, rgb(255, 210, 70));
        }
        $textColor = $red ? rgb(190, 25, 38) : rgb(20, 26, 32);
        if ($rank >= 16) {
            win_draw_text($hdc, $x + 7, $y + 8, 'J', 30, $textColor, 1);
            win_draw_text($hdc, $x + 7, $y + 35, 'O', 30, $textColor, 1);
            win_draw_text($hdc, $x + 7, $y + 62, 'K', 30, $textColor, 1);
            win_draw_text($hdc, $x + 36, $y + 25, 'ER', 19, $textColor, 1);
            return;
        }

        win_draw_text($hdc, $x + 7, $y + 6, rank_label($rank), 36, $textColor, 1);
        win_draw_text($hdc, $x + 9, $y + 43, card_suit_label($card), 24, $textColor, 1);
    }

    private function drawRect(int $hdc, int $x, int $y, int $w, int $h, int $rgb): void
    {
        win_draw_line($hdc, $x, $y, $x + $w, $y, $rgb);
        win_draw_line($hdc, $x + $w, $y, $x + $w, $y + $h, $rgb);
        win_draw_line($hdc, $x + $w, $y + $h, $x, $y + $h, $rgb);
        win_draw_line($hdc, $x, $y + $h, $x, $y, $rgb);
    }
}

class LandlordApp
{
    private int $hWnd = 0;
    private LandlordGame $game;
    private LandlordRenderer $renderer;

    public function __construct()
    {
        $this->game = new LandlordGame();
    }

    public function initWindow(): void
    {
        $this->hWnd = win_create_window('斗地主 - PHP AOT', WINDOW_WIDTH, WINDOW_HEIGHT);
        if ($this->hWnd == 0) {
            echo "错误：窗口创建失败\n";
            return;
        }
        win_show_window($this->hWnd, SW_SHOW);
        $this->renderer = new LandlordRenderer($this->hWnd);
    }

    public function run(): void
    {
        $running = true;
        while ($running) {
            while (true) {
                $msg = win_peek_message();
                if (count($msg) == 0) {
                    break;
                }
                $type = (int)($msg[1] ?? 0);
                if ($type == WM_LBUTTONDOWN) {
                    $this->handleClick(mouse_x((int)($msg[3] ?? 0)), mouse_y((int)($msg[3] ?? 0)));
                } elseif ($type == WM_KEYDOWN && (int)($msg[2] ?? 0) == VK_N) {
                    $this->game->newGame();
                } elseif ($type == WM_QUIT) {
                    $running = false;
                    break;
                }
            }

            if (win_quit_requested()) {
                $running = false;
            }
            if (!$running) {
                break;
            }

            $this->game->updateAi();
            $this->renderer->render($this->game);
            usleep(16000);
        }
    }

    private function handleClick(int $x, int $y): void
    {
        if (in_rect($x, $y, BTN_NEW_X, BTN_Y, BTN_W, BTN_H)) {
            $this->game->newGame();
            return;
        }
        if (in_rect($x, $y, BTN_PLAY_X, BTN_Y, BTN_W, BTN_H)) {
            $this->game->humanPrimaryAction();
            return;
        }
        if (in_rect($x, $y, BTN_PASS_X, BTN_Y, BTN_W, BTN_H)) {
            $this->game->humanSecondaryAction();
            return;
        }

        $idx = $this->renderer->hitTestHand($this->game, $x, $y);
        if ($idx >= 0) {
            $this->game->toggleCardAt($idx);
        }
    }
}

function main(): void
{
    date_default_timezone_set('Asia/Shanghai');
    echo "斗地主 - PHP AOT Win32 示例\n";
    echo "Win32 只传递底层鼠标事件，游戏逻辑由 PHP 实现。\n";

    $app = new LandlordApp();
    $app->initWindow();
    $app->run();
}

