<?php
// '카카오 로그인' 버튼 → 카카오 로그인 화면으로 보내기
declare(strict_types=1);
define('PUBLIC_PAGE', true);
require __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/kakao.php';

if (!kakaoEnabled()) { flash('카카오 로그인이 아직 설정되지 않았어.'); redirect('index.php'); }
if (currentUser()) redirect('index.php');

startSession();
$state = bin2hex(random_bytes(16));   // 돌아왔을 때 우리가 보낸 요청이 맞는지 확인하는 1회용 값
$_SESSION['kakao_state'] = $state;
$_SESSION['kakao_next'] = safeNext($_GET['next'] ?? '');
header('Location: ' . kakaoAuthorizeUrl($state));
exit;
