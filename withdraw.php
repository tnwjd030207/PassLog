<?php
// 회원 탈퇴: 로그인한 본인 계정과 기록(시험·문항·강의 진도·자격증 설정)을 지운다. POST + 확인 문구 입력 필요.
declare(strict_types=1);
define('NICKNAME_PAGE', true);   // 닉네임을 정하기 전(가입 직후)에도 탈퇴할 수 있게
require __DIR__ . '/includes/functions.php';

$me = currentUser();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('nickname.php');
if (trim((string)($_POST['confirm'] ?? '')) !== '탈퇴') {
    flash("탈퇴하려면 확인 칸에 '탈퇴' 라고 입력해줘.");
    redirect('nickname.php');
}

$uid = (int)$me['id'];
$pdo = pdo();
$pdo->beginTransaction();
try {
    q('DELETE FROM lesson_progress WHERE user_id = ?', [$uid]);
    q('DELETE FROM user_certs WHERE user_id = ?', [$uid]);
    q('DELETE FROM tests WHERE user_id = ?', [$uid]);   // test_items 는 FK CASCADE 로 함께 삭제
    q('DELETE FROM users WHERE id = ?', [$uid]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[withdraw] ' . $e->getMessage());
    flash('탈퇴 처리 중 문제가 생겼어. 잠시 후 다시 해줘.');
    redirect('nickname.php');
}
logout();
startSession();
flash('탈퇴했어. 계정과 기록이 모두 지워졌어. 카카오로 다시 로그인하면 새로 가입돼.');
redirect('index.php');
