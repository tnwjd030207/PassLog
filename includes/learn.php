<?php
// 학습 콘텐츠(강의) 목록 / 진도 / 이어서 공부하기
declare(strict_types=1);

/** 자격증의 강의 목록 + 내 진도. [과목 => [단원 => [강의...]]] 와 요약을 돌려줌 */
function lessonTree(array $cert): array
{
    $rows = qall('SELECT l.id, l.code, l.subject, l.topic, l.sort, l.title, l.minutes,
                         lp.status, lp.read_pct, lp.last_pct, lp.updated_at
                    FROM lessons l
                    LEFT JOIN lesson_progress lp ON lp.lesson_id = l.id AND lp.user_id = ?
                   WHERE l.cert_id = ? AND l.active = 1
                   ORDER BY l.subject, l.sort, l.id', [uid(), $cert['id']]);
    $tree = [];
    $sum = ['total' => 0, 'done' => 0, 'started' => 0, 'minutes' => 0, 'by_subject' => []];
    foreach ($rows as $r) {
        $r['id'] = (int)$r['id'];
        $no = (int)$r['subject'];
        $tree[$no][$r['topic']][] = $r;
        $sum['total']++;
        $sum['minutes'] += (int)$r['minutes'];
        $sum['by_subject'][$no] ??= ['total' => 0, 'done' => 0];
        $sum['by_subject'][$no]['total']++;
        if ($r['status'] === 'done') { $sum['done']++; $sum['by_subject'][$no]['done']++; }
        elseif ($r['status'] === 'started') $sum['started']++;
    }
    $sum['pct'] = pct($sum['done'], $sum['total']);
    return ['tree' => $tree, 'sum' => $sum, 'flat' => $rows];
}

/** '이어서 공부하기' 대상: 마지막으로 보던 강의(완료 안 했으면 그 강의, 완료했으면 다음 미완료 강의) */
function resumeLesson(array $cert, array $flat): ?array
{
    if (!$flat) return null;
    $uc = userCert($cert['id']);
    $lastId = (int)($uc['last_lesson_id'] ?? 0);
    $idx = null;
    foreach ($flat as $i => $l) if ($l['id'] === $lastId) $idx = $i;
    if ($idx !== null && $flat[$idx]['status'] !== 'done') return $flat[$idx] + ['resume' => true];
    $start = $idx === null ? 0 : $idx + 1;
    $n = count($flat);
    for ($k = 0; $k < $n; $k++) {
        $l = $flat[($start + $k) % $n];
        if ($l['status'] !== 'done') return $l + ['resume' => $l['status'] === 'started'];
    }
    return null;   // 다 끝냄
}

function loadLesson(int $id): ?array
{
    return q1('SELECT l.*, c.code AS cert_code FROM lessons l JOIN certs c ON c.id = l.cert_id WHERE l.id = ? AND l.active = 1', [$id]);
}

function lessonProgress(int $lessonId): ?array
{
    if (!uid()) return null;
    return q1('SELECT * FROM lesson_progress WHERE user_id = ? AND lesson_id = ?', [uid(), $lessonId]);
}

/** 강의를 열었을 때: 진도 행 만들고, 마지막으로 본 강의로 기억 */
function touchLesson(array $lesson): void
{
    if (!uid()) return;
    q("INSERT INTO lesson_progress (user_id, lesson_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE updated_at = NOW()", [uid(), (int)$lesson['id']]);
    setUserCert((int)$lesson['cert_id'], ['last_lesson_id' => (int)$lesson['id']]);
}

function saveLessonPos(int $lessonId, int $pctNow): void
{
    $pctNow = max(0, min(100, $pctNow));
    q('INSERT INTO lesson_progress (user_id, lesson_id, read_pct, last_pct) VALUES (?, ?, ?, ?)
       ON DUPLICATE KEY UPDATE read_pct = GREATEST(read_pct, VALUES(read_pct)), last_pct = VALUES(last_pct)',
        [uid(), $lessonId, $pctNow, $pctNow]);
}

function completeLesson(int $lessonId, bool $done): void
{
    if ($done) {
        q("INSERT INTO lesson_progress (user_id, lesson_id, status, read_pct, completed_at) VALUES (?, ?, 'done', 100, NOW())
           ON DUPLICATE KEY UPDATE status = 'done', read_pct = 100, completed_at = COALESCE(completed_at, NOW())", [uid(), $lessonId]);
    } else {
        q("UPDATE lesson_progress SET status = 'started', completed_at = NULL WHERE user_id = ? AND lesson_id = ?", [uid(), $lessonId]);
    }
}

/** 같은 자격증 안에서 이전/다음 강의 */
function lessonNeighbors(array $lesson): array
{
    $ids = q('SELECT id FROM lessons WHERE cert_id = ? AND active = 1 ORDER BY subject, sort, id', [(int)$lesson['cert_id']])->fetchAll(PDO::FETCH_COLUMN);
    $ids = array_map('intval', $ids);
    $i = array_search((int)$lesson['id'], $ids, true);
    return [
        'prev' => $i !== false && $i > 0 ? $ids[$i - 1] : null,
        'next' => $i !== false && $i < count($ids) - 1 ? $ids[$i + 1] : null,
        'index' => $i === false ? 0 : $i + 1, 'count' => count($ids),
    ];
}
