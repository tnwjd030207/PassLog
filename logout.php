<?php
declare(strict_types=1);
define('PUBLIC_PAGE', true);
require __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') logout();   // 로그아웃은 POST 로만 (링크로 몰래 로그아웃시키는 것 방지)
header('Location: index.php');
exit;
