<?php
// 자격증 / 과목 / 단원 정보와 "지금 보고 있는 자격증" 상태
declare(strict_types=1);

/** 활성 자격증 전체 (code => row) */
function allCerts(): array
{
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = [];
    foreach (qall('SELECT * FROM certs WHERE active = 1 ORDER BY sort, id') as $c) {
        $c['id'] = (int)$c['id'];
        $c['minutes'] = (int)$c['minutes'];
        $c['pass_total'] = (int)$c['pass_total'];
        $c['pass_subject'] = $c['pass_subject'] === null ? null : (int)$c['pass_subject'];
        $c['subjects'] = [];
        $cache[$c['code']] = $c;
    }
    if ($cache) {
        $byId = [];
        foreach ($cache as $code => $c) $byId[$c['id']] = $code;
        foreach (qall('SELECT cert_id, no, name, exam_count FROM cert_subjects ORDER BY cert_id, no') as $s) {
            $code = $byId[(int)$s['cert_id']] ?? null;
            if ($code) $cache[$code]['subjects'][(int)$s['no']] = ['no' => (int)$s['no'], 'name' => $s['name'], 'count' => (int)$s['exam_count']];
        }
        foreach ($cache as &$c) {
            $c['exam_total'] = array_sum(array_column($c['subjects'], 'count'));
            $c['q_seconds'] = $c['exam_total'] > 0 ? (int)floor($c['minutes'] * 60 / $c['exam_total']) : 60;
        }
        unset($c);
    }
    return $cache;
}

function certById(int $id): ?array
{
    foreach (allCerts() as $c) if ($c['id'] === $id) return $c;
    return null;
}

function certByCode(?string $code): ?array
{
    return allCerts()[(string)$code] ?? null;
}

/**
 * 지금 보고 있는 자격증. ?cert=코드 로 바꾸면 세션에 기억.
 * 회원은 기억된 게 없으면 가장 최근에 푼 자격증, 그것도 없으면 첫 번째.
 */
function currentCert(): array
{
    static $cur = null;
    if ($cur !== null) return $cur;
    $all = allCerts();
    if (!$all) throw new RuntimeException('자격증 데이터가 없어. install.php 를 먼저 실행해줘.');
    startSession();
    $code = $_GET['cert'] ?? $_POST['cert'] ?? null;
    if (is_string($code) && isset($all[$code])) {
        $_SESSION['cert'] = $code;
    } else {
        $code = $_SESSION['cert'] ?? null;
        if (!isset($all[(string)$code]) && uid()) {
            $cid = qv('SELECT cert_id FROM tests WHERE user_id = ? ORDER BY id DESC LIMIT 1', [uid()]);
            $c = $cid ? certById((int)$cid) : null;
            $code = $c['code'] ?? null;
        }
        if (!isset($all[(string)$code])) $code = array_key_first($all);
    }
    return $cur = $all[$code];
}

function subjectName(array $cert, int $no): string
{
    return $cert['subjects'][$no]['name'] ?? ('과목' . $no);
}

/** 단원 목록 (과목 → [단원...]) */
function certTopics(int $certId): array
{
    $out = [];
    foreach (qall('SELECT subject, name, tip FROM topics WHERE cert_id = ? ORDER BY subject, sort, id', [$certId]) as $t) {
        $out[(int)$t['subject']][] = ['name' => $t['name'], 'tip' => $t['tip']];
    }
    return $out;
}

function topicTip(int $certId, string $topic): string
{
    return (string)(qv('SELECT tip FROM topics WHERE cert_id = ? AND name = ?', [$certId, $topic]) ?? '');
}

/** 자격증별 문제 수 (과목 x 난이도) */
function bankCounts(int $certId): array
{
    $out = ['total' => 0, 'by' => []];
    foreach (qall('SELECT subject, difficulty, COUNT(*) n FROM questions WHERE cert_id = ? AND active = 1 GROUP BY subject, difficulty', [$certId]) as $r) {
        $out['by'][(int)$r['subject']][(int)$r['difficulty']] = (int)$r['n'];
        $out['total'] += (int)$r['n'];
    }
    return $out;
}

/** 회원 x 자격증 상태 (D-day, 마지막 강의) */
function userCert(int $certId): array
{
    if (!uid()) return ['exam_date' => null, 'last_lesson_id' => null];
    return q1('SELECT exam_date, last_lesson_id FROM user_certs WHERE user_id = ? AND cert_id = ?', [uid(), $certId])
        ?? ['exam_date' => null, 'last_lesson_id' => null];
}

function setUserCert(int $certId, array $fields): void
{
    if (!uid()) return;
    $allowed = ['exam_date', 'last_lesson_id'];
    $cols = array_values(array_intersect(array_keys($fields), $allowed));
    if (!$cols) return;
    $vals = array_map(fn($c) => $fields[$c], $cols);
    $sql = 'INSERT INTO user_certs (user_id, cert_id, ' . implode(', ', $cols) . ') VALUES (?, ?, ' . inPlaceholders($cols) . ')'
         . ' ON DUPLICATE KEY UPDATE ' . implode(', ', array_map(fn($c) => "$c = VALUES($c)", $cols));
    q($sql, array_merge([uid(), $certId], $vals));
}
