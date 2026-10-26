<?php
require __DIR__ . '/_init.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    q('UPDATE lessons SET active = 1 - active, admin_edited = 1 WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
    flash('강의 공개 상태를 바꿨어.');
    redirect('lessons.php?' . (string)($_POST['back'] ?? ''));
}
$cert = (int)($_GET['cert'] ?? 0);
$where = $cert ? 'WHERE l.cert_id = ?' : '';
$rows = qall(
    "SELECT l.id, l.code, l.subject, l.topic, l.sort, l.title, l.minutes, l.active, l.admin_edited, c.short_name,
            (SELECT COUNT(*) FROM lesson_progress lp WHERE lp.lesson_id = l.id) readers,
            (SELECT COUNT(*) FROM lesson_progress lp WHERE lp.lesson_id = l.id AND lp.status = 'done') done_n
       FROM lessons l JOIN certs c ON c.id = l.cert_id $where ORDER BY c.sort, l.cert_id, l.subject, l.sort, l.id",
    $cert ? [$cert] : []
);
adminHeader('강의', 'lessons');
?>
<h1>강의 관리 <a class="btn small primary" href="lesson.php<?= $cert ? '?cert=' . $cert : '' ?>">+ 새 강의</a></h1>
<form class="admin-filter" method="get">
    <select name="cert" onchange="this.form.submit()"><option value="0">자격증 전체</option><?php foreach (adminCerts() as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $cert === (int)$c['id'] ? 'selected' : '' ?>><?= h($c['short_name']) ?></option><?php endforeach; ?></select>
</form>
<section class="card glass">
    <table class="admin-table">
        <thead><tr><th>자격증</th><th>과목</th><th>순서</th><th>단원</th><th>제목</th><th>분량</th><th>읽은 회원</th><th>완료</th><th>상태</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr class="<?= (int)$r['active'] ? '' : 'dim' ?>">
                <td><?= h($r['short_name']) ?></td><td><?= (int)$r['subject'] ?></td><td><?= (int)$r['sort'] ?></td><td><?= h($r['topic']) ?></td>
                <td><a href="lesson.php?id=<?= (int)$r['id'] ?>"><?= h($r['title']) ?></a><?= (int)$r['admin_edited'] ? ' <span class="tag">수정됨</span>' : '' ?></td>
                <td><?= (int)$r['minutes'] ?>분</td><td><?= (int)$r['readers'] ?></td><td><?= (int)$r['done_n'] ?></td>
                <td><?= (int)$r['active'] ? '공개' : '<span class="tag bad">숨김</span>' ?></td>
                <td><?= adminPostButton('lessons.php', ['action' => 'toggle', 'id' => (int)$r['id'], 'back' => $cert ? 'cert=' . $cert : ''], (int)$r['active'] ? '숨기기' : '공개', 'btn small') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="10" class="muted">강의가 없어.</td></tr><?php endif; ?>
        </tbody>
    </table>
</section>
<?php adminFooter();
