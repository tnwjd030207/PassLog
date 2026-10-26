<?php
// (시간 제한 없는 연습) 힌트 보기 — POST: item_id. 이 요청이 올 때만 힌트를 내려주고 사용 기록을 남긴다.
declare(strict_types=1);
define('GUEST_OK', true);
require __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'error' => 'POST only'], 405);
$itemId = (int)($_POST['item_id'] ?? 0);

[$own, $p] = ownerWhere('te');
$row = q1("SELECT q.hint, ti.hint_used FROM test_items ti
             JOIN tests te ON te.id = ti.test_id
             JOIN questions q ON q.id = ti.question_id
            WHERE ti.id = ? AND $own AND te.status = 'in_progress' AND te.kind = 'practice' AND te.test_date >= ?",
    array_merge([$itemId], $p, [activeSince()]));
if (!$row) json_out(['ok' => false, 'error' => '힌트를 볼 수 없는 문제야. (실전 모의고사에는 힌트가 없어)'], 400);
if (!(int)$row['hint_used']) q('UPDATE test_items SET hint_used = 1, hint_used_at = NOW() WHERE id = ?', [$itemId]);
json_out(['ok' => true, 'hint' => $row['hint']]);
