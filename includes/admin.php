<?php
// 관리자 페이지 공통: 관리자 로그인(일반 회원과 별도), CSRF 토큰, 레이아웃, 페이지 나누기
declare(strict_types=1);

const ADMIN_PER_PAGE = 30;

function adminCurrent(): ?array
{
    static $a = false;
    if ($a !== false) return $a;
    startSession();
    $id = (int)($_SESSION['admin_id'] ?? 0);
    $a = $id ? (q1('SELECT id, username, pw_changed FROM admins WHERE id = ?', [$id]) ?: null) : null;
    if (!$a) unset($_SESSION['admin_id']);
    return $a;
}

function requireAdmin(): array
{
    $a = adminCurrent();
    if (!$a) redirect('login.php');
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') csrfCheck();
    return $a;
}

function csrfToken(): string
{
    startSession();
    if (empty($_SESSION['admin_csrf'])) $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
    return (string)$_SESSION['admin_csrf'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrfToken()) . '">';
}

function csrfCheck(): void
{
    if (!hash_equals(csrfToken(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('요청이 만료됐어. 새로고침 후 다시 해줘.');
    }
}

/** 확인이 필요한 버튼(삭제 등)을 가진 작은 POST 폼 */
function adminPostButton(string $action, array $fields, string $label, string $class = 'btn small', string $confirm = ''): string
{
    $html = '<form method="post" action="' . h($action) . '" class="inline-form"'
        . ($confirm !== '' ? ' onsubmit="return confirm(' . h(json_encode($confirm, JSON_UNESCAPED_UNICODE)) . ');"' : '') . '>' . csrfField();
    foreach ($fields as $k => $v) $html .= '<input type="hidden" name="' . h($k) . '" value="' . h((string)$v) . '">';
    return $html . '<button class="' . h($class) . '" type="submit">' . h($label) . '</button></form>';
}

/** 페이지 번호 링크 */
function adminPager(int $total, int $page, array $query): string
{
    $pages = max(1, (int)ceil($total / ADMIN_PER_PAGE));
    if ($pages <= 1) return '';
    $out = '<nav class="pager">';
    for ($p = max(1, $page - 4); $p <= min($pages, $page + 4); $p++) {
        $out .= $p === $page ? '<b>' . $p . '</b>'
            : '<a href="?' . h(http_build_query(array_merge($query, ['p' => $p]))) . '">' . $p . '</a>';
    }
    return $out . '<span class="muted small">총 ' . number_format($total) . '건</span></nav>';
}

function adminPage(): int
{
    return max(1, (int)($_GET['p'] ?? 1));
}

function adminCerts(): array
{
    return qall('SELECT id, code, name, short_name, active FROM certs ORDER BY sort, id');
}

function adminHeader(string $title, string $active): void
{
    $a = adminCurrent();
    $nav = [
        'dash' => ['index.php', '대시보드'], 'users' => ['users.php', '회원'], 'tests' => ['tests.php', '시험 기록'],
        'questions' => ['questions.php', '문제'], 'lessons' => ['lessons.php', '강의'], 'certs' => ['certs.php', '자격증'],
        'account' => ['account.php', '관리자 계정'],
    ];
    $flash = flash();
    ?><!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="color-scheme" content="dark">
    <title><?= h($title) ?> · 관리자 · <?= h(BRAND_NAME) ?></title>
    <link rel="icon" type="image/svg+xml" href="../assets/brand/favicon.svg">
    <link rel="stylesheet" href="../assets/style.css?v=9">
</head>
<body class="admin">
<header class="topbar">
    <div class="wrap topbar-in">
        <a class="brand" href="index.php"><img src="../assets/brand/mark.svg" alt="" width="28" height="28"> <?= h(BRAND_NAME) ?> <span class="admin-badge">ADMIN</span></a>
        <?php if ($a): ?>
        <nav class="nav" aria-label="관리자 메뉴">
            <?php foreach ($nav as $k => [$href, $label]): ?>
                <a href="<?= $href ?>" class="<?= $active === $k ? 'on' : '' ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="userbox">
            <a href="../index.php" class="btn small" target="_blank" rel="noopener">사이트 보기</a>
            <form method="post" action="logout.php"><?= csrfField() ?><button class="btn small" type="submit">로그아웃</button></form>
        </div>
        <?php endif; ?>
    </div>
</header>
<main class="wrap admin-main">
<?php if ($flash): ?><div class="flash" role="status"><?= h($flash) ?></div><?php endif; ?>
<?php if ($a && !(int)$a['pw_changed']): ?><div class="flash warn" role="alert">초기 비밀번호를 쓰고 있어. <a href="account.php">관리자 계정</a>에서 바로 바꿔줘.</div><?php endif; ?>
<?php
}

function adminFooter(): void
{
    echo "</main>\n</body>\n</html>";
}

function diffLabel(?int $d): string
{
    return [1 => '하', 2 => '중', 3 => '상'][$d ?? 0] ?? '혼합';
}
