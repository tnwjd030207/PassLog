<?php
// 화면 조각 (여러 페이지에서 같이 쓰는 입력 컨트롤)
declare(strict_types=1);

/** 난이도 고르기: 혼합 / 하 / 중 / 상 */
function diffPicker(string $id, int $selected = 0): string
{
    $html = '<div class="seg" role="radiogroup" aria-label="난이도">';
    foreach (DIFF_LABEL as $v => $label) {
        $html .= '<label class="seg-opt d' . $v . '"><input type="radio" name="difficulty" value="' . $v . '"' . ($v === $selected ? ' checked' : '')
               . ' id="' . h($id . '-' . $v) . '"><span>' . ($v === 0 ? '혼합' : '난이도 ' . $label) . '</span></label>';
    }
    return $html . '</div>';
}

/** 문제 수 고르기 */
function countPicker(int $selected = 10, ?int $max = null): string
{
    $html = '<div class="seg small" role="radiogroup" aria-label="문제 수">';
    foreach (PRACTICE_COUNTS as $n) {
        if ($max !== null && $n > $max) continue;
        $html .= '<label class="seg-opt"><input type="radio" name="count" value="' . $n . '"' . ($n === $selected ? ' checked' : '') . '><span>' . $n . '문제</span></label>';
    }
    return $html . '</div>';
}

/** 난이도 별 표시 */
function stars(int $d): string
{
    return '<span class="stars" title="난이도 ' . h(DIFF_LABEL[$d] ?? '') . '">' . str_repeat('★', $d) . str_repeat('☆', 3 - $d) . '</span>';
}

function subjTag(array $cert, int $no): string
{
    return '<span class="tag s' . $no . '" title="' . h(subjectName($cert, $no)) . '">' . ($no) . '과목</span>';
}

/** 진행 중인 시험으로 가는 주소 */
function testUrl(array $t): string
{
    return (int)($t['q_seconds'] ?? 0) > 0 ? 'exam.php' : 'test.php';
}
