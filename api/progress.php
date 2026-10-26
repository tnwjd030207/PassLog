<?php
// 강의 진도 저장 — POST: lesson_id, action=pos(pct) | done | undone   (로그인 필요)
declare(strict_types=1);
require __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'error' => 'POST only'], 405);
$lesson = loadLesson((int)($_POST['lesson_id'] ?? 0));
if (!$lesson) json_out(['ok' => false, 'error' => '강의를 찾을 수 없어.'], 404);
$action = (string)($_POST['action'] ?? 'pos');
if ($action === 'pos') saveLessonPos((int)$lesson['id'], (int)($_POST['pct'] ?? 0));
elseif ($action === 'done') completeLesson((int)$lesson['id'], true);
elseif ($action === 'undone') completeLesson((int)$lesson['id'], false);
else json_out(['ok' => false, 'error' => '알 수 없는 요청이야.'], 400);
$p = lessonProgress((int)$lesson['id']);
$cert = certById((int)$lesson['cert_id']);
$sum = lessonTree($cert)['sum'];
json_out(['ok' => true, 'status' => $p['status'] ?? 'started', 'read_pct' => (int)($p['read_pct'] ?? 0), 'cert_pct' => $sum['pct'], 'done' => $sum['done'], 'total' => $sum['total']]);
