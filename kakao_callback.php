<?php
// 카카오 로그인 후 돌아오는 곳 (카카오디벨로퍼스 > REST API 키 > 리다이렉트 URI 에 이 주소를 등록)
declare(strict_types=1);
define('PUBLIC_PAGE', true);
require __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/kakao.php';

if (!kakaoEnabled()) redirect('index.php');
startSession();
$expected = (string)($_SESSION['kakao_state'] ?? '');
$next = safeNext($_SESSION['kakao_next'] ?? '');
unset($_SESSION['kakao_state'], $_SESSION['kakao_next']);   // 1회용
$fail = 'index.php';   // 실패하면 홈으로 (안내 문구와 함께)

// 사용자가 동의 화면에서 '취소'한 경우
if (isset($_GET['error'])) {
    flash(($_GET['error'] ?? '') === 'access_denied' ? '카카오 로그인을 취소했어.' : '카카오 로그인 중 문제가 생겼어. 다시 해줘.');
    redirect($fail);
}
$state = (string)($_GET['state'] ?? '');
$code = (string)($_GET['code'] ?? '');
if ($expected === '' || !hash_equals($expected, $state) || $code === '') {
    flash('로그인 요청이 만료됐어. 카카오 로그인을 다시 눌러줘.');
    redirect($fail);
}

try {
    $profile = kakaoProfile(kakaoToken($code));
    $r = kakaoSignIn($profile);
} catch (Throwable $e) {
    error_log('[kakao] ' . $e->getMessage());
    flash($e instanceof RuntimeException ? $e->getMessage() : '카카오 로그인 중 문제가 생겼어. 잠시 후 다시 해줘.');
    redirect($fail);
}

loginAs($r['user_id']);   // 비회원으로 풀던 시험도 이 계정으로 옮겨짐
if ($r['new']) {
    // 처음 가입: 닉네임을 직접 정하게 한다 (카카오 닉네임은 쓸 수 있을 때만 미리 채워 둠)
    $_SESSION['nick_suggest'] = $r['suggest'];
    $_SESSION['nick_next'] = $next;
    redirect('nickname.php');
}
redirect($next);
