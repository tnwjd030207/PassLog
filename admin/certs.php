<?php
require __DIR__ . '/_init.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    q('UPDATE certs SET active = 1 - active, admin_edited = 1 WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
    flash('자격증 공개 상태를 바꿨어.');
    redirect('certs.php');
}
$rows = qall(
    "SELECT c.*, (SELECT COUNT(*) FROM cert_subjects s WHERE s.cert_id = c.id) subj_n,
            (SELECT SUM(exam_count) FROM cert_subjects s WHERE s.cert_id = c.id) exam_n,
            (SELECT COUNT(*) FROM questions q WHERE q.cert_id = c.id AND q.active = 1) qn,
            (SELECT COUNT(*) FROM lessons l WHERE l.cert_id = c.id AND l.active = 1) ln
       FROM certs c ORDER BY c.sort, c.id"
);
adminHeader('자격증', 'certs');
?>
<h1>자격증 관리 <a class="btn small primary" href="cert.php">+ 새 자격증</a></h1>
<section class="card glass">
    <table class="admin-table">
        <thead><tr><th>순서</th><th>코드</th><th>이름</th><th>과목</th><th>실전 문항 / 시간</th><th>합격 기준</th><th>문제</th><th>강의</th><th>상태</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $c): ?>
            <tr class="<?= (int)$c['active'] ? '' : 'dim' ?>">
                <td><?= (int)$c['sort'] ?></td><td class="mono"><?= h($c['code']) ?></td>
                <td><span class="cert-dot" style="--cc:<?= h($c['color']) ?>"></span><a href="cert.php?id=<?= (int)$c['id'] ?>"><?= h($c['name']) ?></a><?= (int)$c['admin_edited'] ? ' <span class="tag">수정됨</span>' : '' ?></td>
                <td><?= (int)$c['subj_n'] ?></td><td><?= (int)$c['exam_n'] ?>문항 · <?= (int)$c['minutes'] ?>분</td>
                <td><?= (int)$c['pass_total'] ?>점<?= $c['pass_subject'] === null ? ' (과락 없음)' : ' · 과목 ' . (int)$c['pass_subject'] . '%' ?></td>
                <td><?= (int)$c['qn'] ?></td><td><?= (int)$c['ln'] ?></td>
                <td><?= (int)$c['active'] ? '공개' : '<span class="tag bad">숨김</span>' ?></td>
                <td><?= adminPostButton('certs.php', ['action' => 'toggle', 'id' => (int)$c['id']], (int)$c['active'] ? '숨기기' : '공개', 'btn small') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php adminFooter();
