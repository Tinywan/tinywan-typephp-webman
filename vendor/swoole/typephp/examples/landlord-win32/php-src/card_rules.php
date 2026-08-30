<?php

const SW_SHOW = 5;
const WM_KEYDOWN = 0x0100;
const WM_LBUTTONDOWN = 0x0201;
const WM_QUIT = 0x0012;
const MB_OK = 0x00000000;
const VK_N = 0x4E;

const WINDOW_WIDTH = 1180;
const WINDOW_HEIGHT = 760;
const CARD_W = 68;
const CARD_H = 96;
const CARD_GAP = 36;

const BTN_Y = 506;
const BTN_W = 138;
const BTN_H = 42;
const BTN_PLAY_X = 430;
const BTN_PASS_X = 588;
const BTN_NEW_X = 746;

function rgb(int $r, int $g, int $b): int
{
    return ($r | ($g << 8) | ($b << 16));
}

function mouse_x(int $lParam): int
{
    $x = $lParam & 0xFFFF;
    return $x >= 0x8000 ? $x - 0x10000 : $x;
}

function mouse_y(int $lParam): int
{
    $y = ($lParam >> 16) & 0xFFFF;
    return $y >= 0x8000 ? $y - 0x10000 : $y;
}

function in_rect(int $x, int $y, int $rx, int $ry, int $rw, int $rh): bool
{
    return $x >= $rx && $x <= $rx + $rw && $y >= $ry && $y <= $ry + $rh;
}

function card_rank(int $card): int
{
    if ($card == 52) {
        return 16;
    }
    if ($card == 53) {
        return 17;
    }
    return intdiv($card, 4) + 3;
}

function card_suit(int $card): int
{
    return $card % 4;
}

function rank_label(int $rank): string
{
    $labels = [
        3 => '3', 4 => '4', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 9 => '9',
        10 => '10', 11 => 'J', 12 => 'Q', 13 => 'K', 14 => 'A', 15 => '2',
        16 => '小王', 17 => '大王',
    ];
    return $labels[$rank] ?? '?';
}

function card_label(int $card): string
{
    $rank = card_rank($card);
    if ($rank >= 16) {
        return 'JOKER';
    }
    $suits = ['黑', '红', '梅', '方'];
    return rank_label($rank) . $suits[card_suit($card)];
}

function card_suit_label(int $card): string
{
    $rank = card_rank($card);
    if ($rank >= 16) {
        return '';
    }
    $suits = ['♠', '♥', '♣', '♦'];
    return $suits[card_suit($card)];
}

function sort_cards(array &$cards): void
{
    usort($cards, function (int $a, int $b): int {
        $ra = card_rank($a);
        $rb = card_rank($b);
        return $ra == $rb ? card_suit($a) <=> card_suit($b) : $ra <=> $rb;
    });
}

function rank_counts(array $cards): array
{
    $counts = [];
    foreach ($cards as $card) {
        $rank = card_rank($card);
        $counts[$rank] = ($counts[$rank] ?? 0) + 1;
    }
    ksort($counts);
    return $counts;
}

function is_consecutive(array $ranks): bool
{
    sort($ranks);
    for ($i = 1; $i < count($ranks); $i++) {
        if ($ranks[$i] != $ranks[$i - 1] + 1) {
            return false;
        }
    }
    return true;
}

function analyze_play(array $cards): array
{
    $n = count($cards);
    if ($n == 0) {
        return ['valid' => false, 'type' => 'none', 'main' => 0, 'len' => 0];
    }

    $counts = rank_counts($cards);
    $ranks = array_keys($counts);
    $values = array_values($counts);
    sort($values);

    if ($n == 2 && in_array(16, $ranks, true) && in_array(17, $ranks, true)) {
        return ['valid' => true, 'type' => 'rocket', 'main' => 17, 'len' => 2];
    }
    if ($n == 4 && count($counts) == 1) {
        return ['valid' => true, 'type' => 'bomb', 'main' => $ranks[0], 'len' => 4];
    }
    if ($n == 6 && $values == [1, 1, 4]) {
        foreach ($counts as $rank => $count) {
            if ($count == 4) {
                return ['valid' => true, 'type' => 'four_two_single', 'main' => $rank, 'len' => 6];
            }
        }
    }
    if ($n == 8 && $values == [2, 2, 4]) {
        foreach ($counts as $rank => $count) {
            if ($count == 4) {
                return ['valid' => true, 'type' => 'four_two_pair', 'main' => $rank, 'len' => 8];
            }
        }
    }
    if ($n == 1) {
        return ['valid' => true, 'type' => 'single', 'main' => $ranks[0], 'len' => 1];
    }
    if ($n == 2 && count($counts) == 1) {
        return ['valid' => true, 'type' => 'pair', 'main' => $ranks[0], 'len' => 2];
    }
    if ($n == 3 && count($counts) == 1) {
        return ['valid' => true, 'type' => 'triple', 'main' => $ranks[0], 'len' => 3];
    }
    if ($n == 4 && $values == [1, 3]) {
        foreach ($counts as $rank => $count) {
            if ($count == 3) {
                return ['valid' => true, 'type' => 'triple_single', 'main' => $rank, 'len' => 4];
            }
        }
    }
    if ($n == 5 && $values == [2, 3]) {
        foreach ($counts as $rank => $count) {
            if ($count == 3) {
                return ['valid' => true, 'type' => 'triple_pair', 'main' => $rank, 'len' => 5];
            }
        }
    }
    if ($n >= 5 && count($counts) == $n && max($ranks) < 15 && is_consecutive($ranks)) {
        return ['valid' => true, 'type' => 'straight', 'main' => max($ranks), 'len' => $n];
    }
    if ($n >= 6 && $n % 2 == 0 && min($values) == 2 && max($values) == 2 && max($ranks) < 15 && is_consecutive($ranks)) {
        return ['valid' => true, 'type' => 'pair_sequence', 'main' => max($ranks), 'len' => $n];
    }
    if ($n >= 6 && $n % 3 == 0 && min($values) == 3 && max($values) == 3 && max($ranks) < 15 && is_consecutive($ranks)) {
        return ['valid' => true, 'type' => 'airplane', 'main' => max($ranks), 'len' => $n];
    }

    return ['valid' => false, 'type' => 'invalid', 'main' => 0, 'len' => $n];
}

function can_beat(array $play, array $last): bool
{
    if (!$play['valid']) {
        return false;
    }
    if (!$last['valid']) {
        return true;
    }
    if ($play['type'] == 'rocket') {
        return $last['type'] != 'rocket';
    }
    if ($play['type'] == 'bomb' && $last['type'] != 'bomb' && $last['type'] != 'rocket') {
        return true;
    }
    return $play['type'] == $last['type'] && $play['len'] == $last['len'] && $play['main'] > $last['main'];
}

function play_name(array $play): string
{
    if (!$play['valid']) {
        return '无';
    }
    $names = [
        'single' => '单牌', 'pair' => '对子', 'triple' => '三张',
        'triple_single' => '三带一', 'triple_pair' => '三带一对',
        'four_two_single' => '四带二',
        'four_two_pair' => '四带两对',
        'straight' => '顺子', 'pair_sequence' => '连对',
        'airplane' => '飞机', 'bomb' => '炸弹', 'rocket' => '王炸',
    ];
    return ($names[$play['type']] ?? $play['type']) . ' ' . rank_label($play['main']);
}

function remove_cards(array &$hand, array $cards): void
{
    foreach ($cards as $card) {
        $idx = array_search($card, $hand, true);
        if ($idx !== false) {
            unset($hand[$idx]);
        }
    }
    $hand = array_values($hand);
}

function hand_strength(array $hand): int
{
    $score = 0;
    $counts = rank_counts($hand);
    foreach ($hand as $card) {
        $rank = card_rank($card);
        if ($rank >= 16) {
            $score += 7;
        } elseif ($rank == 15) {
            $score += 4;
        } elseif ($rank >= 13) {
            $score += 2;
        }
    }
    foreach ($counts as $rank => $count) {
        if ($count == 4) {
            $score += 9;
        } elseif ($count == 3) {
            $score += 4;
        } elseif ($count == 2 && $rank >= 12) {
            $score += 2;
        }
    }
    return $score;
}
