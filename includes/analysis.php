<?php
// 단원별 약점 분석 + 공부 코멘트 (자격증별). 단원 공부 팁은 topics.tip (data/certs/*.json 의 tips)
declare(strict_types=1);

const LEVEL_LABEL = ['critical' => '위험', 'weak' => '보강 필요', 'ok' => '무난', 'good' => '탄탄', 'few' => '자료 부족'];

function levelOf(int $t, int $c): string
{
    if ($t < 3) return 'few';
    $p = $c / $t * 100;
    return $p < 40 ? 'critical' : ($p < 60 ? 'weak' : ($p < 80 ? 'ok' : 'good'));
}

/**
 * 끝낸 시험 전체(또는 한 시험)의 단원별 분석. 정답률 낮은 순.
 * 각 항목 = subject, topic, t, c, wrong, hints, p, recent, prior, level, comment
 */
function topicAnalysis(array $cert, ?int $testId = null): array
{
    [$own, $p] = ownerWhere('te');
    $sql = "SELECT q.subject, q.topic, ti.is_correct, ti.hint_used
              FROM test_items ti
              JOIN tests te ON te.id = ti.test_id AND te.status = 'done' AND $own AND te.cert_id = ?
              JOIN questions q ON q.id = ti.question_id";
    $args = array_merge($p, [$cert['id']]);
    if ($testId !== null) { $sql .= ' WHERE te.id = ?'; $args[] = $testId; }
    $sql .= ' ORDER BY te.id, ti.id';
    $by = [];
    foreach (qall($sql, $args) as $r) {
        $k = $r['topic'];
        $by[$k] ??= ['subject' => (int)$r['subject'], 'topic' => $k, 'seq' => [], 'hints' => 0];
        $by[$k]['seq'][] = (int)$r['is_correct'];
        $by[$k]['hints'] += (int)$r['hint_used'];
    }
    $tips = [];
    foreach (qall('SELECT name, tip FROM topics WHERE cert_id = ?', [$cert['id']]) as $r) $tips[$r['name']] = $r['tip'];
    $out = [];
    foreach ($by as $x) {
        $t = count($x['seq']);
        $c = array_sum($x['seq']);
        $recentSeq = array_slice($x['seq'], -5);
        $priorSeq = array_slice($x['seq'], 0, max(0, $t - 5));
        $x += [
            't' => $t, 'c' => $c, 'wrong' => $t - $c, 'p' => (int)round($c / $t * 100),
            'recent' => $t >= 6 ? (int)round(array_sum($recentSeq) / count($recentSeq) * 100) : null,
            'prior'  => $priorSeq ? (int)round(array_sum($priorSeq) / count($priorSeq) * 100) : null,
            'level'  => levelOf($t, $c),
        ];
        unset($x['seq']);
        $x['comment'] = topicComment($x, $tips[$x['topic']] ?? '', $cert);
        $out[] = $x;
    }
    usort($out, fn($a, $b) => [$a['p'], -$a['t']] <=> [$b['p'], -$b['t']]);
    return $out;
}

function topicComment(array $x, string $tip, array $cert): string
{
    $tip = $tip !== '' ? $tip : '개념 강의를 다시 읽고 관련 문제를 더 풀어봐.';
    $head = match ($x['level']) {
        'critical' => "{$x['t']}문제 중 {$x['wrong']}문제를 틀렸어. 지금 가장 먼저 잡아야 할 단원이야.",
        'weak'     => "정답률이 {$x['p']}%라 아직 불안해. 합격선({$cert['pass_total']}%)을 안정적으로 넘기려면 한 번 더 다져야 해.",
        'ok'       => '큰 문제는 없지만 실수하는 유형이 남아 있어. 틀린 문제만 오답노트로 가볍게 복습하면 돼.',
        'good'     => '잘하고 있어. 새 문제 위주로 가끔 확인만 해도 충분해.',
        default    => '아직 푼 문제가 적어서 판단하기 일러. 더 풀어보면 정확해져.',
    };
    $extra = '';
    if ($x['recent'] !== null && $x['prior'] !== null) {
        if ($x['recent'] - $x['prior'] >= 15) $extra .= ' 최근 정답률이 올라가는 중이야.';
        elseif ($x['prior'] - $x['recent'] >= 15) $extra .= ' 최근 정답률이 오히려 떨어졌어. 개념이 덜 굳은 신호야.';
    }
    if ($x['t'] >= 3 && $x['hints'] / $x['t'] >= 0.5) $extra .= ' 힌트를 자주 써서 맞힌 경우가 많아. 힌트 없이 먼저 풀어봐.';
    if ($x['level'] === 'good' || ($x['level'] === 'few' && $x['p'] >= 50)) return $head . $extra;
    return $head . $extra . ' 공부법: ' . $tip;
}

/** 전체 코멘트: 과목 과락 위험 + 우선순위 */
function overallComments(array $cert, array $topics, array $subj): array
{
    $msgs = [];
    $cut = $cert['pass_subject'];
    foreach ($cert['subjects'] as $no => $s) {
        $st = $subj[$no] ?? ['t' => 0, 'c' => 0];
        if ($st['t'] < 5) continue;
        $p = (int)round($st['c'] / $st['t'] * 100);
        $name = $no . '과목(' . $s['name'] . ')';
        if ($cut !== null && $p < $cut) $msgs[] = ['bad', "{$name} 정답률이 {$p}%로 과락 기준({$cut}%) 아래야. 총점이 높아도 이 과목 때문에 불합격할 수 있으니 최우선으로 올려야 해."];
        elseif ($cut !== null && $p < $cut + 15) $msgs[] = ['warn', "{$name} 정답률이 {$p}%라 과락 선({$cut}%)에 가까워. 안전 구간(60%+)까지 올리자."];
        elseif ($cut === null && $p < $cert['pass_total']) $msgs[] = ['warn', "{$name} 정답률이 {$p}%야. 과락은 없지만 총점을 깎는 과목이니 보강하자."];
    }
    $weak = array_values(array_filter($topics, fn($x) => in_array($x['level'], ['critical', 'weak'], true) && $x['t'] >= 3));
    if ($weak) {
        $top = array_slice($weak, 0, 3);
        $names = implode(', ', array_map(fn($x) => $x['topic'] . "({$x['p']}%)", $top));
        $msgs[] = ['info', '우선순위 TOP' . count($top) . ": {$names}. 이 단원 강의를 먼저 읽고 → 오답노트 복습 → 그 단원만 골라서 연습 순서로 반복해봐."];
        $bySubj = array_count_values(array_map(fn($x) => $x['subject'], $weak));
        arsort($bySubj);
        $topSubj = (int)array_key_first($bySubj);
        if (($bySubj[$topSubj] ?? 0) >= 2) {
            $msgs[] = ['info', "약점이 {$topSubj}과목(" . subjectName($cert, $topSubj) . ")에 몰려 있어. 이 과목은 실전에서 " . ($cert['subjects'][$topSubj]['count'] ?? 0) . '문제가 나오니 여기부터 잡으면 점수가 빨리 올라.'];
        }
    } elseif ($topics) {
        $msgs[] = ['ok', '뚜렷한 약점 단원은 아직 없어. 이제는 실전 모의고사로 시간 배분과 집중력을 점검해봐.'];
    }
    if (!$topics) $msgs[] = ['info', '아직 끝낸 시험이 없어. 한 번 풀면 여기에 단원별 분석이 나와.'];
    return $msgs;
}

/** 여러 번 틀린 문제 TOP N */
function repeatWrong(array $cert, int $limit = 5): array
{
    return qall("SELECT q.id, q.subject, q.topic, q.question, COUNT(*) tries, SUM(ti.is_correct = 0) wrong_n
                   FROM test_items ti JOIN tests te ON te.id = ti.test_id AND te.status = 'done' AND te.user_id = ? AND te.cert_id = ?
                   JOIN questions q ON q.id = ti.question_id
                  GROUP BY q.id HAVING wrong_n >= 2 ORDER BY wrong_n DESC, tries DESC LIMIT ?", [uid(), $cert['id'], $limit]);
}

function renderTopicCards(array $cert, array $topics, int $max = 0): void
{
    foreach ($max ? array_slice($topics, 0, $max) : $topics as $x): ?>
        <div class="an-row lv-<?= h($x['level']) ?>">
            <div class="an-head">
                <span class="tag s<?= (int)$x['subject'] ?>"><?= (int)$x['subject'] ?>과목</span>
                <b><?= h($x['topic']) ?></b>
                <span class="lv lv-<?= h($x['level']) ?>"><?= h(LEVEL_LABEL[$x['level']]) ?></span>
                <span class="an-num"><?= $x['p'] ?>% <small>(<?= $x['wrong'] ?>번 틀림 / <?= $x['t'] ?>문제)</small></span>
            </div>
            <div class="track"><i class="<?= $x['p'] < 40 ? 'bad' : ($x['p'] < 70 ? 'warn' : 'ok') ?>" style="--w:<?= $x['p'] ?>%"></i></div>
            <p class="an-comment"><?= h($x['comment']) ?></p>
            <p class="an-links small"><a href="learn.php?cert=<?= h($cert['code']) ?>#t-<?= h(md5($x['topic'])) ?>">강의 보기</a> · <a href="start.php?cert=<?= h($cert['code']) ?>&amp;topic=<?= urlencode($x['topic']) ?>">이 단원만 풀기</a></p>
        </div>
    <?php endforeach;
}
