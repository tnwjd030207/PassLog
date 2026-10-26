<?php
require __DIR__ . '/_init.php';
requireAdmin();

$n = fn(string $sql, array $p = []) => (int)qv($sql, $p);
$stats = [
    ['회원', $n('SELECT COUNT(*) FROM users'), '오늘 가입 ' . $n('SELECT COUNT(*) FROM users WHERE created_at >= CURDATE()')],
    ['오늘 로그인', $n('SELECT COUNT(*) FROM users WHERE last_login >= CURDATE()'), '최근 7일 ' . $n('SELECT COUNT(*) FROM users WHERE last_login >= CURDATE() - INTERVAL 6 DAY')],
    ['끝낸 시험', $n("SELECT COUNT(*) FROM tests WHERE status = 'done'"), '오늘 ' . $n("SELECT COUNT(*) FROM tests WHERE status = 'done' AND test_date = CURDATE()")],
    ['비회원 시험', $n('SELECT COUNT(*) FROM tests WHERE user_id IS NULL'), '진행 중 ' . $n("SELECT COUNT(*) FROM tests WHERE user_id IS NULL AND status = 'in_progress'")],
    ['문제', $n('SELECT COUNT(*) FROM questions WHERE active = 1'), '숨김 ' . $n('SELECT COUNT(*) FROM questions WHERE active = 0')],
    ['강의', $n('SELECT COUNT(*) FROM lessons WHERE active = 1'), '숨김 ' . $n('SELECT COUNT(*) FROM lessons WHERE active = 0')],
];
$perCert = qall(
    "SELECT c.id, c.name, c.active,
            (SELECT COUNT(*) FROM questions q WHERE q.cert_id = c.id AND q.active = 1) qn,
            (SELECT COUNT(*) FROM lessons l WHERE l.cert_id = c.id AND l.active = 1) ln,
            (SELECT COUNT(*) FROM tests t WHERE t.cert_id = c.id AND t.status = 'done') tn,
            (SELECT ROUND(AVG(t.score)) FROM tests t WHERE t.cert_id = c.id AND t.status = 'done') avg_score
       FROM certs c ORDER BY c.sort, c.id"
);
$recentUsers = qall('SELECT id, nickname, created_at, last_login FROM users ORDER BY id DESC LIMIT 8');
$recentTests = qall(
    "SELECT t.id, t.kind, t.status, t.score, t.total_cnt, t.started_at, c.short_name, u.nickname, t.user_id
       FROM tests t JOIN certs c ON c.id = t.cert_id LEFT JOIN users u ON u.id = t.user_id
      ORDER BY t.id DESC LIMIT 10"
);
adminHeader('대시보드', 'dash');
?>
<h1>관리자 대시보드</h1>
<div class="admin-stats">
    <?php foreach ($stats as [$label, $val, $sub]): ?>
        <div class="card glass stat"><span class="muted small"><?= h($label) ?></span><b><?= number_format($val) ?></b><span class="muted small"><?= h($sub) ?></span></div>
    <?php endforeach; ?>
</div>

<section class="card glass">
    <h2>자격증별 현황</h2>
    <table class="admin-table">
        <thead><tr><th>자격증</th><th>상태</th><th>문제</th><th>강의</th><th>끝낸 시험</th><th>평균 점수</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($perCert as $c): ?>
            <tr><td><?= h($c['name']) ?></td><td><?= (int)$c['active'] ? '공개' : '<span class="tag bad">숨김</span>' ?></td>
                <td><a href="questions.php?cert=<?= (int)$c['id'] ?>"><?= (int)$c['qn'] ?></a></td>
                <td><a href="lessons.php?cert=<?= (int)$c['id'] ?>"><?= (int)$c['ln'] ?></a></td>
                <td><a href="tests.php?cert=<?= (int)$c['id'] ?>"><?= (int)$c['tn'] ?></a></td>
                <td><?= $c['avg_score'] === null ? '-' : (int)$c['avg_score'] . '점' ?></td>
                <td><a class="btn small" href="cert.php?id=<?= (int)$c['id'] ?>">설정</a></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<div class="grid-2">
    <section class="card glass">
        <h2>최근 가입 <a class="small" href="users.php">전체 보기</a></h2>
        <table class="admin-table">
            <thead><tr><th>#</th><th>닉네임</th><th>가입</th><th>최근 로그인</th></tr></thead>
            <tbody>
            <?php foreach ($recentUsers as $u): ?>
                <tr><td><?= (int)$u['id'] ?></td><td><a href="user.php?id=<?= (int)$u['id'] ?>"><?= h($u['nickname'] ?? '(닉네임 설정 전)') ?></a></td>
                    <td><?= h(substr((string)$u['created_at'], 0, 16)) ?></td><td><?= h(substr((string)$u['last_login'], 0, 16)) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$recentUsers): ?><tr><td colspan="4" class="muted">아직 회원이 없어.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </section>
    <section class="card glass">
        <h2>최근 시험 <a class="small" href="tests.php">전체 보기</a></h2>
        <table class="admin-table">
            <thead><tr><th>#</th><th>자격증</th><th>종류</th><th>응시자</th><th>점수</th></tr></thead>
            <tbody>
            <?php foreach ($recentTests as $t): ?>
                <tr><td><a href="test.php?id=<?= (int)$t['id'] ?>"><?= (int)$t['id'] ?></a></td><td><?= h($t['short_name']) ?></td>
                    <td><?= $t['kind'] === 'exam' ? '실전' : '연습' ?></td>
                    <td><?= $t['user_id'] ? h($t['nickname'] ?? '#' . $t['user_id']) : '<span class="muted">비회원</span>' ?></td>
                    <td><?= $t['status'] === 'done' ? (int)$t['score'] . '점' : '<span class="muted">진행 중</span>' ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$recentTests): ?><tr><td colspan="5" class="muted">아직 시험 기록이 없어.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </section>
</div>
<?php adminFooter();
