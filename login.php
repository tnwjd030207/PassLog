<?php
// 로그인 화면은 따로 없음 → 바로 카카오 로그인으로 보냄 (예전 링크 호환용)
declare(strict_types=1);
define('PUBLIC_PAGE', true);
require __DIR__ . '/includes/functions.php';

$next = safeNext($_GET['next'] ?? '');
if (currentUser()) redirect($next);
if (!kakaoEnabled()) { flash('지금은 로그인할 수 없어. 잠시 후 다시 와줘.'); redirect('index.php'); }
redirect('kakao_login.php' . ($next !== 'index.php' ? '?next=' . urlencode($next) : ''));
