<?php
// 시험 엔진: 문제 뽑기, 시험 만들기, 문제별 타이머/일시정지, 채점
declare(strict_types=1);

const TIME_GRACE = 3;   // 시간 초과 판정 여유(초) — 네트워크 지연 보정

// ---- 합격 판정 -------------------------------------------------------------
/**
 * $subjects = [과목번호 => ['t' => 문항수, 'c' => 정답수]]
 * 점수 = 정답률 x 100 (모든 자격증 100점 환산), 과락은 자격증 설정(pass_subject %)
 */
function judge(array $cert, array $subjects): array
{
    $t = array_sum(array_column($subjects, 't'));
    $c = array_sum(array_column($subjects, 'c'));
    $score = $t > 0 ? (int)round($c / $t * 100) : 0;
    $reasons = [];
    if ($score < $cert['pass_total']) $reasons[] = "총점 {$cert['pass_total']}점 미만";
    if ($cert['pass_subject'] !== null) {
        foreach ($subjects as $no => $s) {
            if ($s['t'] > 0 && $s['c'] * 100 < $cert['pass_subject'] * $s['t']) {
                $reasons[] = subjectName($cert, (int)$no) . " 과락 ({$cert['pass_subject']}% 미만)";
            }
        }
    }
    return ['pass' => !$reasons, 'reasons' => $reasons, 'score' => $score];
}

/** 시험의 과목별 결과 (예전 SQLD 기록은 s1/s2 컬럼에서 복원) */
function testSubjects(array $test): array
{
    if (!empty($test['subject_json'])) {
        $d = json_decode((string)$test['subject_json'], true);
        if (is_array($d)) {
            $out = [];
            foreach ($d as $no => $s) $out[(int)$no] = ['t' => (int)$s['t'], 'c' => (int)($s['c'] ?? 0)];
            ksort($out);
            return $out;
        }
    }
    $out = [];
    if ((int)$test['s1_total'] > 0) $out[1] = ['t' => (int)$test['s1_total'], 'c' => (int)$test['s1_correct']];
    if ((int)$test['s2_total'] > 0) $out[2] = ['t' => (int)$test['s2_total'], 'c' => (int)$test['s2_correct']];
    return $out;
}

function kindLabel(array $test): string
{
    if ($test['kind'] === 'exam') return '실전 모의고사';
    return (int)($test['q_seconds'] ?? 0) > 0 ? '연습 · 시간제한' : '연습';
}

// ---- 문제 뽑기 -------------------------------------------------------------
function weightedSample(array $items, int $n): array
{
    $out = [];
    $pool = array_values($items);
    while ($n > 0 && $pool) {
        $sum = 0.0;
        foreach ($pool as $p) $sum += $p['w'];
        $r = mt_rand() / mt_getrandmax() * $sum;
        $acc = 0.0;
        $idx = count($pool) - 1;
        foreach ($pool as $i => $p) {
            $acc += $p['w'];
            if ($r <= $acc) { $idx = $i; break; }
        }
        $out[] = $pool[$idx];
        array_splice($pool, $idx, 1);
        $n--;
    }
    return $out;
}

/** 단원별 정답률 (회원만) */
function topicAccuracy(int $certId): array
{
    if (!uid()) return [];
    $map = [];
    foreach (qall(
        "SELECT q.topic, SUM(ti.is_correct) c, COUNT(*) t
           FROM test_items ti
           JOIN tests te ON te.id = ti.test_id AND te.status = 'done' AND te.user_id = ? AND te.cert_id = ?
           JOIN questions q ON q.id = ti.question_id
          GROUP BY q.topic", [uid(), $certId]) as $r) {
        $map[$r['topic']] = (int)$r['c'] / max(1, (int)$r['t']);
    }
    return $map;
}

/**
 * 한 과목에서 n문제를 고른다.
 *  $difficulty: 0 혼합 / 1 하 / 2 중 / 3 상 — 고른 난이도가 모자라면 가까운 난이도로 채운다.
 *  $mode: smart(틀린 문제 복습 30% + 약한 단원 가중) | random
 * 돌려주는 값: [문제 id 배열, 고른 난이도로 못 채워서 다른 난이도로 채운 개수]
 */
function pickQuestions(int $certId, int $subject, int $n, string $mode, int $difficulty, array $exclude = [], ?string $topic = null): array
{
    $u = uid();
    $sql = "SELECT q.id, q.topic, q.difficulty,
                   (SELECT COUNT(*) FROM test_items a JOIN tests ta ON ta.id = a.test_id
                     WHERE a.question_id = q.id AND ta.user_id = ?) AS seen,
                   (SELECT b.is_correct FROM test_items b JOIN tests tb ON tb.id = b.test_id
                     WHERE b.question_id = q.id AND tb.status = 'done' AND tb.user_id = ?
                     ORDER BY tb.id DESC, b.id DESC LIMIT 1) AS last_correct,
                   (SELECT MAX(c.id) FROM test_items c JOIN tests tc ON tc.id = c.test_id
                     WHERE c.question_id = q.id AND tc.user_id = ?) AS last_seen
              FROM questions q
             WHERE q.cert_id = ? AND q.subject = ? AND q.active = 1";
    $params = [$u, $u, $u, $certId, $subject];
    if ($topic !== null && $topic !== '') { $sql .= ' AND q.topic = ?'; $params[] = $topic; }
    $all = qall($sql, $params);

    $ex = array_flip($exclude);
    $pool = array_values(array_filter($all, fn($r) => !isset($ex[(int)$r['id']])));
    if (count($pool) < $n) $pool = $all;

    // 난이도: 고른 난이도 → 가까운 난이도 순서로 채움
    $order = match ($difficulty) {
        1 => [1, 2, 3], 2 => [2, 1, 3], 3 => [3, 2, 1], default => [0],
    };
    $sel = [];
    $fallback = 0;
    foreach ($order as $k => $d) {
        $need = $n - count($sel);
        if ($need <= 0) break;
        $taken = array_flip(array_map(fn($r) => (int)$r['id'], $sel));
        $cand = array_values(array_filter($pool, fn($r) => ($d === 0 || (int)$r['difficulty'] === $d) && !isset($taken[(int)$r['id']])));
        $got = pickByMode($cand, $need, $mode, $certId);
        if ($k > 0) $fallback += count($got);
        $sel = array_merge($sel, $got);
    }
    shuffle($sel);
    return [array_map(fn($r) => (int)$r['id'], $sel), $fallback];
}

function pickByMode(array $pool, int $n, string $mode, int $certId): array
{
    if ($n <= 0 || !$pool) return [];
    if ($mode !== 'smart' || !uid()) {
        shuffle($pool);
        return array_slice($pool, 0, $n);
    }
    $acc = topicAccuracy($certId);
    $wrong = $unseen = $seenOk = [];
    foreach ($pool as $r) {
        if ((int)$r['seen'] === 0) {
            $r['w'] = 1 + 2 * (1 - ($acc[$r['topic']] ?? 0.5));
            $unseen[] = $r;
        } elseif ($r['last_correct'] !== null && (int)$r['last_correct'] === 0) {
            $wrong[] = $r;
        } else {
            $seenOk[] = $r;
        }
    }
    shuffle($wrong);
    $quota = (int)ceil($n * 0.3);
    $sel = array_slice($wrong, 0, $quota);
    $sel = array_merge($sel, weightedSample($unseen, $n - count($sel)));
    if (count($sel) < $n) {
        usort($seenOk, fn($a, $b) => (int)$a['last_seen'] <=> (int)$b['last_seen']);
        $sel = array_merge($sel, array_slice($seenOk, 0, $n - count($sel)));
    }
    if (count($sel) < $n) $sel = array_merge($sel, array_slice($wrong, $quota, $n - count($sel)));
    return $sel;
}

// ---- 시험 상태 -------------------------------------------------------------
/** 어제 이후 만든 시험만 '진행 중'으로 인정 */
function activeSince(): string
{
    return date('Y-m-d', strtotime('-1 day'));
}

function loadTest(int $id): ?array
{
    [$own, $p] = ownerWhere('t');
    return q1("SELECT t.* FROM tests t WHERE t.id = ? AND $own", array_merge([$id], $p));
}

/** 진행 중인 시험 (자격증 상관없이 하나). 시간 제한 시험은 시간을 먼저 맞춰 본다. */
function currentTest(): ?array
{
    [$own, $p] = ownerWhere('t');
    $rows = qall("SELECT t.* FROM tests t WHERE $own AND t.test_date >= ? AND t.status = 'in_progress' ORDER BY t.id DESC",
        array_merge($p, [activeSince()]));
    foreach ($rows as $row) {
        if ((int)$row['q_seconds'] > 0) {
            $row = timedSync($row);
            if ($row['status'] === 'done') continue;
        }
        return $row;
    }
    return null;
}

/**
 * 새 시험 만들기.
 * $opt: cert_id, kind(practice|exam), count, subject(0=전체), topic, difficulty(0~3), mode(smart|random), timed(bool)
 */
function createTest(array $opt): array
{
    if ($cur = currentTest()) return $cur;
    $cert = certById((int)$opt['cert_id']);
    if (!$cert) throw new RuntimeException('자격증을 찾을 수 없어.');
    $kind = ($opt['kind'] ?? 'practice') === 'exam' ? 'exam' : 'practice';
    if ($kind === 'exam' && isGuest()) throw new RuntimeException('실전 모의고사는 로그인하면 볼 수 있어.');
    $difficulty = max(0, min(3, (int)($opt['difficulty'] ?? 0)));
    $mode = ($opt['mode'] ?? 'smart') === 'random' ? 'random' : 'smart';
    $timed = $kind === 'exam' || !empty($opt['timed']);

    // 과목별 문항수 결정
    $plan = [];
    $subjectFilter = null;
    $topic = null;
    if ($kind === 'exam') {
        foreach ($cert['subjects'] as $s) $plan[$s['no']] = $s['count'];
        $mode = 'random';
    } else {
        $count = (int)($opt['count'] ?? 10);
        if (!in_array($count, PRACTICE_COUNTS, true)) $count = 10;
        if (isGuest()) $count = min($count, GUEST_MAX_QUESTIONS);
        $subj = (int)($opt['subject'] ?? 0);
        $topic = trim((string)($opt['topic'] ?? '')) ?: null;
        if ($topic !== null) {
            $subj = (int)(qv('SELECT subject FROM topics WHERE cert_id = ? AND name = ?', [$cert['id'], $topic]) ?? 0);
            if (!$subj) $topic = null;
        }
        if ($subj && isset($cert['subjects'][$subj])) {
            $plan[$subj] = $count;
            $subjectFilter = $subj;
        } else {
            // 실제 시험 비율대로 나눔 (최대 나머지 방식)
            $total = $cert['exam_total'];
            $rem = [];
            foreach ($cert['subjects'] as $s) {
                $exact = $count * $s['count'] / $total;
                $plan[$s['no']] = (int)floor($exact);
                $rem[$s['no']] = $exact - floor($exact);
            }
            arsort($rem);
            $left = $count - array_sum($plan);
            foreach (array_keys($rem) as $no) { if ($left-- <= 0) break; $plan[$no]++; }
        }
    }

    // 데일리 연습은 오늘 이미 나온 문제를 되도록 피함 (회원)
    $used = [];
    if ($kind === 'practice' && uid()) {
        $used = array_map('intval', q(
            "SELECT DISTINCT ti.question_id FROM test_items ti JOIN tests te ON te.id = ti.test_id
              WHERE te.user_id = ? AND te.cert_id = ? AND te.test_date = ?", [uid(), $cert['id'], today()]
        )->fetchAll(PDO::FETCH_COLUMN));
    }

    $ids = [];
    $fallback = 0;
    $subjTotals = [];
    foreach ($plan as $no => $n) {
        if ($n <= 0) continue;
        [$got, $fb] = pickQuestions($cert['id'], (int)$no, $n, $mode, $difficulty, $used, $topic);
        $ids = array_merge($ids, $got);
        $fallback += $fb;
        $subjTotals[$no] = ['t' => count($got), 'c' => 0];
    }
    if (!$ids) throw new RuntimeException('이 조건에 맞는 문제가 아직 없어. 다른 조건으로 골라줘.');
    $need = array_sum($plan);
    if ($kind === 'exam' && count($ids) < $need) {
        throw new RuntimeException('문제은행에 실전 1회분(' . $need . '문제)이 아직 모자라. 연습 모드로 풀어줘.');
    }

    $pdo = pdo();
    $pdo->beginTransaction();
    try {
        $uidv = uid() ?: null;
        $gk = uid() ? null : guestKey();
        if (!uid()) q('DELETE FROM tests WHERE user_id IS NULL AND started_at < NOW() - INTERVAL 2 DAY');   // 오래된 비회원 기록 정리
        q('INSERT INTO tests (user_id, guest_key, cert_id, test_date, kind, difficulty, subject_filter, topic_filter, total_cnt, q_seconds, cur_pos, cur_started_ts, subject_json, s1_total, s2_total)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NULL, ?, ?, ?)', [
            $uidv, $gk, $cert['id'], today(), $kind, $difficulty ?: null, $subjectFilter, $topic, count($ids),
            $timed ? $cert['q_seconds'] : null, json_encode($subjTotals),
            $subjTotals[1]['t'] ?? 0, $subjTotals[2]['t'] ?? 0,
        ]);
        $testId = (int)$pdo->lastInsertId();
        // 과목 순서대로 배치 (실제 시험처럼)
        $order = qall('SELECT id, subject FROM questions WHERE id IN (' . inPlaceholders($ids) . ')', $ids);
        $subjOf = array_column($order, 'subject', 'id');
        usort($ids, fn($a, $b) => ((int)$subjOf[$a] <=> (int)$subjOf[$b]));
        foreach ($ids as $i => $qid) q('INSERT INTO test_items (test_id, question_id, position) VALUES (?, ?, ?)', [$testId, $qid, $i + 1]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    if ($fallback > 0 && $difficulty > 0) {
        flash('난이도 ' . DIFF_LABEL[$difficulty] . ' 문제가 모자라서 ' . $fallback . '문제는 가까운 난이도로 채웠어.');
    }
    return loadTest($testId);
}

function abandonTest(int $testId): bool
{
    [$own, $p] = ownerWhere('t');
    return q("DELETE t FROM tests t WHERE t.id = ? AND t.status = 'in_progress' AND $own", array_merge([$testId], $p))->rowCount() > 0;
}

// ---- 문제별 타이머 (서버 기준) -------------------------------------------------
/** 지금 문제에 쓴 시간(초). 일시정지 중이면 멈춘 시점까지만. */
function timedElapsed(array $t, ?int $now = null): int
{
    $now ??= time();
    if ($t['cur_started_ts'] === null) return 0;
    $end = $t['paused_ts'] !== null ? (int)$t['paused_ts'] : $now;
    return max(0, $end - (int)$t['cur_started_ts']);
}

/**
 * 시간 제한 시험의 시계를 맞춘다: 제한 시간을 넘긴 문제는 '시간 초과'로 넘기고,
 * 마지막 문제까지 다 지나면 채점까지 한다. (탭을 닫았다 와도 서버 시간 기준으로 정확)
 */
function timedSync(array $t): array
{
    if ($t['status'] !== 'in_progress' || (int)$t['q_seconds'] <= 0) return $t;
    $now = time();
    $limit = (int)$t['q_seconds'];
    if ($t['cur_started_ts'] === null) {   // 첫 화면을 연 순간부터 시작
        q('UPDATE tests SET cur_started_ts = ?, started_at = NOW() WHERE id = ? AND cur_started_ts IS NULL', [$now, (int)$t['id']]);
        return loadTestRaw((int)$t['id']);
    }
    if ($t['paused_ts'] !== null) return $t;
    $elapsed = $now - (int)$t['cur_started_ts'];
    if ($elapsed < $limit + TIME_GRACE) return $t;

    $pos = (int)$t['cur_pos'];
    $total = (int)$t['total_cnt'];
    $start = (int)$t['cur_started_ts'];
    while ($pos <= $total && $now - $start >= $limit + TIME_GRACE) {
        q('UPDATE test_items SET timed_out = 1, spent_sec = ? WHERE test_id = ? AND position = ? AND answered_at IS NULL', [$limit, (int)$t['id'], $pos]);
        $start += $limit;
        $pos++;
    }
    q('UPDATE tests SET cur_pos = ?, cur_started_ts = ? WHERE id = ?', [$pos, $start, (int)$t['id']]);
    if ($pos > $total) return finishTest((int)$t['id']);
    return loadTestRaw((int)$t['id']);
}

function loadTestRaw(int $id): array
{
    return q1('SELECT * FROM tests WHERE id = ?', [$id]);
}

/** 현재 문제에 답(또는 0=넘기기)을 내고 다음 문제로 */
function timedAnswer(array $t, int $pos, int $answer, bool $auto = false): array
{
    $t = timedSync($t);
    if ($t['status'] === 'done') return $t;
    if ($t['paused_ts'] !== null) throw new RuntimeException('일시정지 중이야. 다시 시작을 눌러줘.');
    if ($pos !== (int)$t['cur_pos']) throw new RuntimeException('이미 지나간 문제야. 화면을 새로고침할게.');
    $elapsed = timedElapsed($t);
    $spent = min((int)$t['q_seconds'], $elapsed);
    // 브라우저가 '시간 종료'로 자동 제출한 경우, 서버 시계로도 시간이 거의 다 됐을 때만 시간 초과로 기록
    $timedOut = $auto && $elapsed >= (int)$t['q_seconds'] - 2 ? 1 : 0;
    q('UPDATE test_items SET user_answer = ?, answered_at = NOW(), spent_sec = ?, timed_out = ? WHERE test_id = ? AND position = ?',
        [$answer >= 1 && $answer <= 4 ? $answer : null, $spent, $timedOut, (int)$t['id'], $pos]);
    $next = $pos + 1;
    q('UPDATE tests SET cur_pos = ?, cur_started_ts = ? WHERE id = ?', [$next, time(), (int)$t['id']]);
    if ($next > (int)$t['total_cnt']) return finishTest((int)$t['id']);
    return loadTestRaw((int)$t['id']);
}

function timedPause(array $t): array
{
    $t = timedSync($t);
    if ($t['status'] === 'done' || $t['paused_ts'] !== null) return $t;
    q('UPDATE tests SET paused_ts = ?, pause_count = pause_count + 1 WHERE id = ? AND paused_ts IS NULL', [time(), (int)$t['id']]);
    return loadTestRaw((int)$t['id']);
}

function timedResume(array $t): array
{
    if ($t['status'] === 'done' || $t['paused_ts'] === null) return $t;
    $now = time();
    $paused = max(0, $now - (int)$t['paused_ts']);
    q('UPDATE tests SET paused_ts = NULL, pause_total_sec = pause_total_sec + ?, cur_started_ts = cur_started_ts + ? WHERE id = ? AND paused_ts IS NOT NULL',
        [$paused, $paused, (int)$t['id']]);
    return loadTestRaw((int)$t['id']);
}

/** 시간 제한 시험 화면에 보낼 상태 (현재 문제만 — 앞으로 나올 문제는 미리 안 보냄) */
function timedPayload(array $t, array $cert): array
{
    $base = [
        'status' => $t['status'], 'pos' => (int)$t['cur_pos'], 'total' => (int)$t['total_cnt'],
        'limit' => (int)$t['q_seconds'], 'paused' => $t['paused_ts'] !== null,
        'pause_count' => (int)$t['pause_count'], 'pause_total' => (int)$t['pause_total_sec']
            + ($t['paused_ts'] !== null ? max(0, time() - (int)$t['paused_ts']) : 0),
    ];
    if ($t['status'] === 'done') return $base + ['url' => 'result.php?id=' . (int)$t['id']];
    $base['left'] = max(0, (int)$t['q_seconds'] - timedElapsed($t));
    if ($base['paused']) return $base + ['item' => null];   // 일시정지 중엔 문제를 가림
    $it = q1('SELECT ti.position, ti.hint_used, q.subject, q.topic, q.difficulty, q.question, q.code_block, q.choices, q.mono,
                     CASE WHEN ti.hint_used = 1 THEN q.hint ELSE NULL END AS hint
                FROM test_items ti JOIN questions q ON q.id = ti.question_id
               WHERE ti.test_id = ? AND ti.position = ?', [(int)$t['id'], (int)$t['cur_pos']]);
    if (!$it) return $base + ['item' => null];
    return $base + ['item' => [
        'pos' => (int)$it['position'], 'subject' => (int)$it['subject'], 'subject_name' => subjectName($cert, (int)$it['subject']),
        'topic' => $it['topic'], 'difficulty' => (int)$it['difficulty'], 'question' => $it['question'],
        'code' => (string)($it['code_block'] ?? ''), 'choices' => json_decode($it['choices'], true), 'mono' => (int)$it['mono'],
        'hint_used' => (int)$it['hint_used'], 'hint' => $it['hint'], 'can_hint' => $t['kind'] === 'practice',
    ]];
}

// ---- 채점 -----------------------------------------------------------------
function finishTest(int $testId): array
{
    $pdo = pdo();
    $pdo->beginTransaction();
    $test = q1('SELECT * FROM tests WHERE id = ? FOR UPDATE', [$testId]);
    if (!$test) { $pdo->rollBack(); throw new RuntimeException('시험을 찾을 수 없어.'); }
    if ($test['status'] === 'done') { $pdo->commit(); return $test; }
    $cert = certById((int)$test['cert_id']);

    $items = qall('SELECT ti.id, ti.user_answer, ti.hint_used, ti.timed_out, ti.spent_sec, q.answer, q.subject
                     FROM test_items ti JOIN questions q ON q.id = ti.question_id WHERE ti.test_id = ?', [$testId]);
    $subj = [];
    $hints = $timeouts = $spentSum = 0;
    foreach ($items as $it) {
        $ok = $it['user_answer'] !== null && (int)$it['user_answer'] === (int)$it['answer'] ? 1 : 0;
        q('UPDATE test_items SET is_correct = ? WHERE id = ?', [$ok, (int)$it['id']]);
        $no = (int)$it['subject'];
        $subj[$no] ??= ['t' => 0, 'c' => 0];
        $subj[$no]['t']++;
        $subj[$no]['c'] += $ok;
        $hints += (int)$it['hint_used'];
        $timeouts += (int)$it['timed_out'];
        $spentSum += (int)($it['spent_sec'] ?? 0);
    }
    ksort($subj);
    $j = judge($cert, $subj);
    $pauseTotal = (int)$test['pause_total_sec'];
    if ($test['paused_ts'] !== null) $pauseTotal += max(0, time() - (int)$test['paused_ts']);
    if ((int)$test['q_seconds'] > 0) {
        $dur = $spentSum;   // 문제별로 잰 시간의 합 (일시정지 제외)
    } else {
        $dur = max(0, (int)qv('SELECT TIMESTAMPDIFF(SECOND, started_at, NOW()) FROM tests WHERE id = ?', [$testId]));
    }
    $correct = array_sum(array_column($subj, 'c'));
    q("UPDATE tests SET status = 'done', finished_at = NOW(), duration_sec = ?, correct_cnt = ?, score = ?, subject_json = ?,
            s1_correct = ?, s2_correct = ?, hints_used = ?, timeouts = ?, passed = ?, paused_ts = NULL, pause_total_sec = ?
      WHERE id = ?", [
        $dur, $correct, $j['score'], json_encode($subj), $subj[1]['c'] ?? null, $subj[2]['c'] ?? null,
        $hints, $timeouts, $j['pass'] ? 1 : 0, $pauseTotal, $testId,
    ]);
    $pdo->commit();
    return loadTestRaw($testId);
}
