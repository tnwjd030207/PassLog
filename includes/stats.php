<?php
// 회원 통계 (자격증별)
declare(strict_types=1);

function summaryStats(int $certId, string $kind): array
{
    $r = q1("SELECT COUNT(*) n, AVG(score) avg_score, MAX(score) best, SUM(passed) pass_n,
                    SUM(hints_used) hints, SUM(correct_cnt) correct, SUM(total_cnt) total
               FROM tests WHERE user_id = ? AND cert_id = ? AND status = 'done' AND kind = ?", [uid(), $certId, $kind]);
    return [
        'n'    => (int)$r['n'],
        'avg'  => $r['avg_score'] === null ? null : (int)round((float)$r['avg_score']),
        'best' => $r['best'] === null ? null : (int)$r['best'],
        'pass' => (int)$r['pass_n'],
        'acc'  => (int)$r['total'] > 0 ? (int)round((int)$r['correct'] / (int)$r['total'] * 100) : null,
    ];
}

/** 과목별 누적 정답 [과목번호 => ['c','t']] */
function subjectStats(array $cert): array
{
    $out = [];
    foreach ($cert['subjects'] as $no => $s) $out[$no] = ['c' => 0, 't' => 0];
    foreach (qall("SELECT q.subject, SUM(ti.is_correct) c, COUNT(*) t
                     FROM test_items ti
                     JOIN tests te ON te.id = ti.test_id AND te.status = 'done' AND te.user_id = ? AND te.cert_id = ?
                     JOIN questions q ON q.id = ti.question_id
                    GROUP BY q.subject", [uid(), $cert['id']]) as $r) {
        $out[(int)$r['subject']] = ['c' => (int)$r['c'], 't' => (int)$r['t']];
    }
    return $out;
}

function topicStats(int $certId): array
{
    return array_map(fn($r) => [
        'subject' => (int)$r['subject'], 'topic' => $r['topic'], 'c' => (int)$r['c'], 't' => (int)$r['t'],
    ], qall("SELECT q.subject, q.topic, SUM(ti.is_correct) c, COUNT(*) t
               FROM test_items ti
               JOIN tests te ON te.id = ti.test_id AND te.status = 'done' AND te.user_id = ? AND te.cert_id = ?
               JOIN questions q ON q.id = ti.question_id
              GROUP BY q.subject, q.topic
              ORDER BY SUM(ti.is_correct) / COUNT(*) ASC, COUNT(*) DESC", [uid(), $certId]));
}

function recentTests(int $certId, int $limit, ?string $kind): array
{
    $sql = "SELECT * FROM tests WHERE user_id = ? AND cert_id = ? AND status = 'done'";
    $p = [uid(), $certId];
    if ($kind !== null) { $sql .= ' AND kind = ?'; $p[] = $kind; }
    $sql .= ' ORDER BY id DESC LIMIT ?';
    $p[] = $limit;
    return array_reverse(qall($sql, $p));
}

/** 연속 학습일 — 시험을 끝냈거나 강의를 공부한 날 */
function streak(): int
{
    if (!uid()) return 0;
    $dates = q("SELECT d FROM (
                  SELECT test_date d FROM tests WHERE user_id = ? AND status = 'done'
                  UNION SELECT DATE(updated_at) FROM lesson_progress WHERE user_id = ?
                ) x ORDER BY d DESC", [uid(), uid()])->fetchAll(PDO::FETCH_COLUMN);
    if (!$dates) return 0;
    $set = array_flip($dates);
    $d = new DateTimeImmutable(today());
    if (!isset($set[$d->format('Y-m-d')])) $d = $d->modify('-1 day');
    $n = 0;
    while (isset($set[$d->format('Y-m-d')])) { $n++; $d = $d->modify('-1 day'); }
    return $n;
}

function bankSeen(int $certId): array
{
    return [
        'total' => (int)qv('SELECT COUNT(*) FROM questions WHERE cert_id = ? AND active = 1', [$certId]),
        'seen'  => (int)qv("SELECT COUNT(DISTINCT ti.question_id) FROM test_items ti
                             JOIN tests te ON te.id = ti.test_id AND te.status = 'done' AND te.user_id = ? AND te.cert_id = ?", [uid(), $certId]),
    ];
}
