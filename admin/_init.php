<?php
// 관리자 페이지 공통 시작 파일 (일반 회원 로그인과는 별개로 관리자 로그인을 확인)
declare(strict_types=1);
define('PUBLIC_PAGE', true);
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/admin.php';
header('X-Robots-Tag: noindex, nofollow');
