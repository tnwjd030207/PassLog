<?php
// 제출 및 채점 — POST: test_id
declare(strict_types=1);
define('GUEST_OK', true);
require __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'error' => 'POST only'], 405);
$t = loadTest((int)($_POST['test_id'] ?? 0));
if (!$t || ($t['status'] !== 'done' && $t['test_date'] < activeSince())) json_out(['ok' => false, 'error' => '제출할 수 있는 시험이 아니야.'], 400);
try {
    $t = finishTest((int)$t['id']);
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => $e->getMessage()], 500);
}
json_out(['ok' => true, 'url' => 'result.php?id=' . (int)$t['id']]);
