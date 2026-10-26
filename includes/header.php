<?php
// 공통 머리말. 사용 전에 $pageTitle, $active('home'|'learn'|'test'|'analysis'|'history'|'wrong'), $page(배경 강도) 를 정해줘.
$pageTitle = $pageTitle ?? BRAND_TAGLINE;
$active = $active ?? '';
$page = $page ?? 'default';
$me = currentUser();
$showCert = $showCert ?? true;   // 자격증 선택 메뉴 표시 여부
try { $certList = allCerts(); $cc = $certList ? currentCert() : null; } catch (Throwable $e) { $certList = []; $cc = null; }
$nav = $me ? [
    'home'     => ['index.php', '홈'],
    'learn'    => ['learn.php', '학습'],
    'test'     => ['start.php', '문제 풀기'],
    'analysis' => ['analysis.php', '분석'],
    'history'  => ['history.php', '기록'],
    'wrong'    => ['wrong.php', '오답노트'],
] : [
    'home'  => ['index.php', '홈'],
    'learn' => ['learn.php', '학습'],
    'test'  => ['start.php', '문제 풀기'],
];
$self = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
$certSwitchable = in_array($self, ['index.php', 'learn.php', 'start.php', 'analysis.php', 'history.php', 'wrong.php'], true);
$flashMsg = flash();
?><!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <meta name="theme-color" content="#070a14">
    <meta name="description" content="<?= h(BRAND_KO . ' — ' . BRAND_TAGLINE . '. SQLD · 정보처리기사 · ADsP · 리눅스마스터 · 네트워크관리사 문제 풀이와 강의') ?>">
    <title><?= h($pageTitle) ?> · <?= h(BRAND_NAME) ?></title>
    <link rel="icon" type="image/svg+xml" href="assets/brand/favicon.svg">
    <link rel="stylesheet" href="assets/style.css?v=8">
    <script type="importmap">{"imports":{"three":"./assets/vendor/three/three.module.js"}}</script>
</head>
<body data-page="<?= h($page) ?>">
<canvas id="bg3d" aria-hidden="true"></canvas>
<div class="bg-vignette" aria-hidden="true"></div>
<header class="topbar">
    <div class="wrap topbar-in">
        <a class="brand" href="index.php" aria-label="<?= h(BRAND_NAME) ?> 홈">
            <img src="assets/brand/mark.svg" width="30" height="30" alt="">
            <span class="brand-name">Pass<b>Log</b></span>
        </a>
        <?php if ($certList && $cc && $showCert): ?>
            <?php if ($certSwitchable): ?>
            <form class="cert-switch" method="get" action="<?= h($self) ?>">
                <label class="sr-only" for="cert-sel">자격증 선택</label>
                <select id="cert-sel" name="cert" onchange="this.form.submit()">
                    <?php foreach ($certList as $c): ?>
                        <option value="<?= h($c['code']) ?>" <?= $c['code'] === $cc['code'] ? 'selected' : '' ?>><?= h($c['short_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <noscript><button class="btn small">바꾸기</button></noscript>
            </form>
            <?php else: ?>
                <span class="cert-pill" style="--cc:<?= h($cc['color']) ?>"><?= h($cc['short_name']) ?></span>
            <?php endif; ?>
        <?php endif; ?>
        <nav class="nav" aria-label="주 메뉴">
            <?php foreach ($nav as $key => [$href, $label]): ?>
                <a href="<?= $href ?>" class="<?= $active === $key ? 'on' : '' ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="userbox">
            <?php if ($me): ?>
                <a class="uname" href="nickname.php" title="닉네임 바꾸기"><?= h(displayName($me)) ?></a>
                <form method="post" action="logout.php"><button class="btn small" type="submit">로그아웃</button></form>
            <?php else: ?>
                <?php $here = safeNext(basename($_SERVER['SCRIPT_NAME'] ?? 'index.php') . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : '')); ?>
                <?= kakaoButton($here, '카카오로 로그인') ?>
            <?php endif; ?>
        </div>
    </div>
</header>
<main class="wrap">
<?php if ($flashMsg): ?><div class="flash" role="status"><?= h($flashMsg) ?></div><?php endif; ?>
