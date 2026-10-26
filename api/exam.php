<?php
// 시간 제한 시험 API (POST: test_id, action=state|answer|pause|resume|hint, pos, answer)
// 시간 계산은 전부 서버 시계 기준이라 브라우저 시계를 바꿔도 소용없어.
declare(strict_types=1);
define('GUEST_OK', true);
require __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'error' => 'POST only'], 405);

$testId = (int)($_POST['test_id'] ?? 0);
$action = (string)($_POST['action'] ?? 'state');
$t = loadTest($testId);
if (!$t) json_out(['ok' => false, 'error' => '시험을 찾을 수 없어.'], 404);
if ((int)$t['q_seconds'] <= 0) json_out(['ok' => false, 'error' => '시간 제한 시험이 아니야.'], 400);
$cert = certById((int)$t['cert_id']);
if ($t['status'] === 'done') json_out(['ok' => true, 'state' => timedPayload($t, $cert)]);
if ($t['test_date'] < activeSince()) json_out(['ok' => false, 'error' => '기간이 지난 시험이야.'], 400);

try {
    switch ($action) {
        case 'state':
            $t = timedSync($t);
            break;
        case 'answer':
            $t = timedAnswer($t, (int)($_POST['pos'] ?? 0), (int)($_POST['answer'] ?? 0), ($_POST['auto'] ?? '0') === '1');
            break;
        case 'pause':
            $t = timedPause($t);
            break;
        case 'resume':
            $t = timedResume($t);
            break;
        case 'hint':
            $t = timedSync($t);
            if ($t['kind'] !== 'practice') throw new RuntimeException('실전 모의고사에는 힌트가 없어.');
            if ($t['status'] === 'done' || $t['paused_ts'] !== null) break;
            if ((int)($_POST['pos'] ?? 0) !== (int)$t['cur_pos']) throw new RuntimeException('지금 문제가 아니야.');
            q('UPDATE test_items SET hint_used = 1, hint_used_at = COALESCE(hint_used_at, NOW()) WHERE test_id = ? AND position = ?', [(int)$t['id'], (int)$t['cur_pos']]);
            break;
        default:
            json_out(['ok' => false, 'error' => '알 수 없는 요청이야.'], 400);
    }
} catch (RuntimeException $e) {
    $t = loadTestRaw((int)$t['id']);
    json_out(['ok' => false, 'error' => $e->getMessage(), 'state' => timedPayload(timedSync($t), $cert)]);
}
json_out(['ok' => true, 'state' => timedPayload($t, $cert)]);
