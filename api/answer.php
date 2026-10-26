<?php
// (시간 제한 없는 연습) 답 선택 저장 — POST: item_id, answer 1~4
declare(strict_types=1);
define('GUEST_OK', true);
require __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'error' => 'POST only'], 405);
$itemId = (int)($_POST['item_id'] ?? 0);
$answer = (int)($_POST['answer'] ?? 0);
if ($answer < 1 || $answer > 4) json_out(['ok' => false, 'error' => '보기 번호가 올바르지 않아.'], 400);

[$own, $p] = ownerWhere('te');
$row = q1("SELECT ti.id FROM test_items ti JOIN tests te ON te.id = ti.test_id
            WHERE ti.id = ? AND $own AND te.status = 'in_progress' AND te.test_date >= ? AND te.q_seconds IS NULL",
    array_merge([$itemId], $p, [activeSince()]));
if (!$row) json_out(['ok' => false, 'error' => '저장할 수 없는 문제야. 새로고침해줘.'], 400);
q('UPDATE test_items SET user_answer = ?, answered_at = NOW() WHERE id = ?', [$answer, $itemId]);
json_out(['ok' => true]);
