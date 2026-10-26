<?php
require __DIR__ . '/_init.php';
requireAdmin();

$qstr = trim((string)($_GET['q'] ?? ''));
$plan = (string)($_GET['plan'] ?? '');
$where = ['1 = 1'];
$args = [];
if ($qstr !== '') {
    $where[] = '(u.nickname LIKE ? OR u.kakao_id = ? OR u.id = ?)';
    array_push($args, '%' . $qstr . '%', $qstr, ctype_digit($qstr) ? (int)$qstr : 0);
}
if (in_array($plan, ['free', 'premium'], true)) { $where[] = 'u.plan = ?'; $args[] = $plan; }
$w = implode(' AND ', $where);
$total = (int)qv("SELECT COUNT(*) FROM users u WHERE $w", $args);
$page = adminPage();
$rows = qall(
    "SELECT u.*, (SELECT COUNT(*) FROM tests t WHERE t.user_id = u.id AND t.status = 'done') tests_n,
            (SELECT COUNT(*) FROM lesson_progress lp WHERE lp.user_id = u.id AND lp.status = 'done') lessons_n
       FROM users u WHERE $w ORDER BY u.id DESC LIMIT " . ADMIN_PER_PAGE . ' OFFSET ' . (($page - 1) * ADMIN_PER_PAGE),
    $args
);
adminHeader('회원', 'users');
?>
<h1>회원 관리</h1>
<form class="admin-filter" method="get">
    <input type="search" name="q" value="<?= h($qstr) ?>" placeholder="닉네임 / 회원번호 / 카카오 회원번호">
    <select name="plan"><option value="">요금제 전체</option><option value="free" <?= $plan === 'free' ? 'selected' : '' ?>>free</option><option value="premium" <?= $plan === 'premium' ? 'selected' : '' ?>>premium</option></select>
    <button class="btn small" type="submit">검색</button>
</form>
<section class="card glass">
    <table class="admin-table">
        <thead><tr><th>#</th><th>닉네임</th><th>카카오 회원번호</th><th>요금제</th><th>끝낸 시험</th><th>완료 강의</th><th>가입</th><th>최근 로그인</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $u): ?>
            <tr>
                <td><?= (int)$u['id'] ?></td>
                <td><a href="user.php?id=<?= (int)$u['id'] ?>"><?= h($u['nickname'] ?? '(닉네임 설정 전)') ?></a></td>
                <td class="mono"><?= h($u['kakao_id'] ?? '-') ?></td>
                <td><?= $u['plan'] === 'premium' ? '<span class="tag ok">premium</span>' : 'free' ?></td>
                <td><?= (int)$u['tests_n'] ?></td><td><?= (int)$u['lessons_n'] ?></td>
                <td><?= h(substr((string)$u['created_at'], 0, 16)) ?></td><td><?= h(substr((string)$u['last_login'], 0, 16)) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="8" class="muted">해당하는 회원이 없어.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <?= adminPager($total, $page, ['q' => $qstr, 'plan' => $plan]) ?>
</section>
<?php adminFooter();
