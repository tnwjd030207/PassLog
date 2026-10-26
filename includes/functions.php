<?php
// 모든 페이지가 맨 처음 불러오는 파일.
//   PUBLIC_PAGE : 로그인 상관없이 열리는 페이지 (login, register, install 등)
//   GUEST_OK    : 비회원도 쓸 수 있는 페이지 (홈, 연습 문제, 결과, 강의 보기)
//   그 외       : 로그인 필수 (기록, 오답노트, 분석, 실전 모의고사 ...)
declare(strict_types=1);

require_once __DIR__ . '/core.php';
require_once __DIR__ . '/certs.php';
require_once __DIR__ . '/engine.php';
require_once __DIR__ . '/stats.php';
require_once __DIR__ . '/learn.php';
require_once __DIR__ . '/ui.php';
require_once __DIR__ . '/kakao.php';

if (PHP_SAPI !== 'cli') {
    sameOriginOrDie();
    if (!defined('PUBLIC_PAGE')) {
        if (!defined('GUEST_OK')) requireLogin();
        requireNickname();
    }
}

// ---- 문제은행/콘텐츠 불러오기 (install.php 에서 사용) ---------------------------
function seedCerts(string $dataDir): array
{
    $certs = json_decode((string)file_get_contents($dataDir . '/certs.json'), true);
    if (!is_array($certs)) throw new RuntimeException('data/certs.json 을 읽지 못했어.');
    $log = [];
    foreach ($certs as $i => $c) {
        q('INSERT INTO certs (code, name, short_name, org, description, exam_note, minutes, pass_total, pass_subject, color, sort)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
           ON DUPLICATE KEY UPDATE name = IF(admin_edited = 1, name, VALUES(name)), short_name = IF(admin_edited = 1, short_name, VALUES(short_name)), org = IF(admin_edited = 1, org, VALUES(org)), description = IF(admin_edited = 1, description, VALUES(description)), exam_note = IF(admin_edited = 1, exam_note, VALUES(exam_note)), minutes = IF(admin_edited = 1, minutes, VALUES(minutes)), pass_total = IF(admin_edited = 1, pass_total, VALUES(pass_total)), pass_subject = IF(admin_edited = 1, pass_subject, VALUES(pass_subject)), color = IF(admin_edited = 1, color, VALUES(color)), sort = IF(admin_edited = 1, sort, VALUES(sort))', [
            $c['code'], $c['name'], $c['short'], $c['org'], $c['description'], $c['exam_note'],
            (int)$c['minutes'], (int)$c['pass_total'], $c['pass_subject'] === null ? null : (int)$c['pass_subject'], $c['color'], $i,
        ]);
        $certId = (int)qv('SELECT id FROM certs WHERE code = ?', [$c['code']]);
        $certEdited = (int)qv('SELECT admin_edited FROM certs WHERE id = ?', [$certId]) === 1;   // 관리자가 고친 자격증은 과목 설정 유지
        foreach ($c['subjects'] as $s) {
            q($certEdited ? 'INSERT IGNORE INTO cert_subjects (cert_id, no, name, exam_count) VALUES (?, ?, ?, ?)'
                : 'INSERT INTO cert_subjects (cert_id, no, name, exam_count) VALUES (?, ?, ?, ?)
               ON DUPLICATE KEY UPDATE name = VALUES(name), exam_count = VALUES(exam_count)', [$certId, (int)$s['no'], $s['name'], (int)$s['count']]);
        }
        $file = $dataDir . '/certs/' . $c['code'] . '.json';
        if (!is_file($file)) { $log[] = "{$c['name']}: 콘텐츠 파일 없음"; continue; }
        $d = json_decode((string)file_get_contents($file), true);
        if (!is_array($d)) throw new RuntimeException("{$file} 을 읽지 못했어.");

        foreach ($d['topics'] ?? [] as $subj => $names) {
            foreach ($names as $k => $name) {
                q('INSERT INTO topics (cert_id, subject, name, tip, sort) VALUES (?, ?, ?, ?, ?)
                   ON DUPLICATE KEY UPDATE subject = IF(admin_edited = 1, subject, VALUES(subject)), tip = IF(admin_edited = 1, tip, VALUES(tip)), sort = IF(admin_edited = 1, sort, VALUES(sort))',
                    [$certId, (int)$subj, $name, (string)($d['tips'][$name] ?? ''), $k]);
            }
        }
        $questions = $d['questions'] ?? [];
        if (!empty($d['questions_file'])) {
            $questions = json_decode((string)file_get_contents($dataDir . '/../' . $d['questions_file']), true) ?: [];
        }
        $nq = seedQuestions($certId, $questions);
        $nl = 0;
        foreach ($d['lessons'] ?? [] as $l) {
            q('INSERT INTO lessons (cert_id, code, subject, topic, sort, title, minutes, body) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
               ON DUPLICATE KEY UPDATE cert_id = IF(admin_edited = 1, cert_id, VALUES(cert_id)), subject = IF(admin_edited = 1, subject, VALUES(subject)), topic = IF(admin_edited = 1, topic, VALUES(topic)), sort = IF(admin_edited = 1, sort, VALUES(sort)), title = IF(admin_edited = 1, title, VALUES(title)), minutes = IF(admin_edited = 1, minutes, VALUES(minutes)), body = IF(admin_edited = 1, body, VALUES(body))',
                [$certId, $l['code'], (int)$l['subject'], $l['topic'], (int)$l['order'], $l['title'], (int)$l['minutes'], $l['body']]);
            $nl++;
        }
        $log[] = "{$c['name']}: 문제 {$nq}개 · 강의 {$nl}개";
    }
    return $log;
}

function seedQuestions(int $certId, array $data): int
{
    $n = 0;
    foreach ($data as $q) {
        q('INSERT INTO questions (cert_id, code, subject, topic, difficulty, question, code_block, choices, answer, hint, explanation, mono)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
           ON DUPLICATE KEY UPDATE cert_id = IF(admin_edited = 1, cert_id, VALUES(cert_id)), subject = IF(admin_edited = 1, subject, VALUES(subject)), topic = IF(admin_edited = 1, topic, VALUES(topic)), difficulty = IF(admin_edited = 1, difficulty, VALUES(difficulty)), question = IF(admin_edited = 1, question, VALUES(question)), code_block = IF(admin_edited = 1, code_block, VALUES(code_block)), choices = IF(admin_edited = 1, choices, VALUES(choices)), answer = IF(admin_edited = 1, answer, VALUES(answer)), hint = IF(admin_edited = 1, hint, VALUES(hint)), explanation = IF(admin_edited = 1, explanation, VALUES(explanation)), mono = IF(admin_edited = 1, mono, VALUES(mono))', [
            $certId, $q['code'], (int)$q['subject'], $q['topic'], (int)$q['difficulty'], $q['question'],
            ($q['code_block'] ?? '') !== '' ? $q['code_block'] : null,
            json_encode($q['choices'], JSON_UNESCAPED_UNICODE),
            (int)$q['answer'], $q['hint'], $q['explanation'], (int)$q['mono'],
        ]);
        $n++;
    }
    return $n;
}
