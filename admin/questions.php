<?php
require __DIR__ . '/_init.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    q('UPDATE questions SET active = 1 - active, admin_edited = 1 WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
    flash('문제 공개 상태를 바꿨어.');
    redirect('questions.php?' . (string)($_POST['back'] ?? ''));
}

$f = [
    'cert' => (int)($_GET['cert'] ?? 0), 'subject' => (int)($_GET['subject'] ?? 0), 'topic' => (string)($_GET['topic'] ?? ''),
    'diff' => (int)($_GET['diff'] ?? 0), 'active' => (string)($_GET['active'] ?? ''), 'q' => trim((string)($_GET['q'] ?? '')),
];
$where = ['1 = 1'];
$args = [];
if ($f['cert']) { $where[] = 'q.cert_id = ?'; $args[] = $f['cert']; }
if ($f['subject']) { $where[] = 'q.subject = ?'; $args[] = $f['subject']; }
if ($f['topic'] !== '') { $where[] = 'q.topic = ?'; $args[] = $f['topic']; }
if (in_array($f['diff'], [1, 2, 3], true)) { $where[] = 'q.difficulty = ?'; $args[] = $f['diff']; }
if ($f['active'] === '1' || $f['active'] === '0') { $where[] = 'q.active = ?'; $args[] = (int)$f['active']; }
if ($f['q'] !== '') { $where[] = '(q.question LIKE ? OR q.code = ?)'; array_push($args, '%' . $f['q'] . '%', $f['q']); }
$w = implode(' AND ', $where);
$total = (int)qv("SELECT COUNT(*) FROM questions q WHERE $w", $args);
$page = adminPage();
$rows = qall(
    "SELECT q.id, q.code, q.subject, q.topic, q.difficulty, q.question, q.active, q.admin_edited, c.short_name,
            (SELECT COUNT(*) FROM test_items ti WHERE ti.question_id = q.id AND ti.is_correct IS NOT NULL) tries,
            (SELECT ROUND(AVG(ti.is_correct) * 100) FROM test_items ti WHERE ti.question_id = q.id AND ti.is_correct IS NOT NULL) acc
       FROM questions q JOIN certs c ON c.id = q.cert_id WHERE $w ORDER BY q.cert_id, q.subject, q.id
      LIMIT " . ADMIN_PER_PAGE . ' OFFSET ' . (($page - 1) * ADMIN_PER_PAGE),
    $args
);
$topics = $f['cert'] ? qall('SELECT DISTINCT topic FROM questions WHERE cert_id = ? ORDER BY topic', [$f['cert']]) : [];
$back = http_build_query(array_filter($f + ['p' => $page]));
adminHeader('문제', 'questions');
?>
<h1>문제 관리 <a class="btn small primary" href="question.php<?= $f['cert'] ? '?cert=' . $f['cert'] : '' ?>">+ 새 문제</a></h1>
<form class="admin-filter" method="get">
    <select name="cert" onchange="this.form.submit()"><option value="0">자격증 전체</option><?php foreach (adminCerts() as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $f['cert'] === (int)$c['id'] ? 'selected' : '' ?>><?= h($c['short_name']) ?></option><?php endforeach; ?></select>
    <select name="subject"><option value="0">과목 전체</option><?php for ($i = 1; $i <= 5; $i++): ?><option value="<?= $i ?>" <?= $f['subject'] === $i ? 'selected' : '' ?>><?= $i ?>과목</option><?php endfor; ?></select>
    <?php if ($topics): ?><select name="topic"><option value="">단원 전체</option><?php foreach ($topics as $t): ?><option <?= $f['topic'] === $t['topic'] ? 'selected' : '' ?>><?= h($t['topic']) ?></option><?php endforeach; ?></select><?php endif; ?>
    <select name="diff"><option value="0">난이도 전체</option><option value="1" <?= $f['diff'] === 1 ? 'selected' : '' ?>>하</option><option value="2" <?= $f['diff'] === 2 ? 'selected' : '' ?>>중</option><option value="3" <?= $f['diff'] === 3 ? 'selected' : '' ?>>상</option></select>
    <select name="active"><option value="">공개 상태 전체</option><option value="1" <?= $f['active'] === '1' ? 'selected' : '' ?>>공개</option><option value="0" <?= $f['active'] === '0' ? 'selected' : '' ?>>숨김</option></select>
    <input type="search" name="q" value="<?= h($f['q']) ?>" placeholder="문제 내용 / 코드">
    <button class="btn small" type="submit">검색</button>
</form>
<section class="card glass">
    <table class="admin-table">
        <thead><tr><th>코드</th><th>자격증</th><th>과목·단원</th><th>난이도</th><th>문제</th><th>풀이 수</th><th>정답률</th><th>상태</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr class="<?= (int)$r['active'] ? '' : 'dim' ?>">
                <td class="mono"><?= h($r['code']) ?><?= (int)$r['admin_edited'] ? ' <span class="tag" title="관리자가 수정한 문제는 install.php 를 다시 실행해도 덮어쓰지 않아">수정됨</span>' : '' ?></td>
                <td><?= h($r['short_name']) ?></td><td><?= (int)$r['subject'] ?> · <?= h($r['topic']) ?></td>
                <td><?= diffLabel((int)$r['difficulty']) ?></td>
                <td><a href="question.php?id=<?= (int)$r['id'] ?>"><?= h(mb_strimwidth((string)$r['question'], 0, 60, '…')) ?></a></td>
                <td><?= (int)$r['tries'] ?></td><td><?= $r['acc'] === null ? '-' : (int)$r['acc'] . '%' ?></td>
                <td><?= (int)$r['active'] ? '공개' : '<span class="tag bad">숨김</span>' ?></td>
                <td><?= adminPostButton('questions.php', ['action' => 'toggle', 'id' => (int)$r['id'], 'back' => $back], (int)$r['active'] ? '숨기기' : '공개', 'btn small') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="9" class="muted">해당하는 문제가 없어.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <?= adminPager($total, $page, array_filter($f)) ?>
</section>
<?php adminFooter();
