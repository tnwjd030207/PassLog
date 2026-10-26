<?php
require __DIR__ . '/_init.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'delete') {
        q('DELETE FROM tests WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
        flash('시험 기록을 삭제했어.');
    } elseif ($action === 'purge_guest') {
        $n = q("DELETE FROM tests WHERE user_id IS NULL AND started_at < NOW() - INTERVAL 1 DAY")->rowCount();
        flash("하루 지난 비회원 시험 {$n}건을 정리했어.");
    } elseif ($action === 'purge_stale') {
        $n = q("DELETE FROM tests WHERE status = 'in_progress' AND started_at < NOW() - INTERVAL 7 DAY")->rowCount();
        flash("7일 넘게 안 끝낸 시험 {$n}건을 정리했어.");
    }
    redirect('tests.php?' . http_build_query(array_intersect_key($_GET, array_flip(['cert', 'kind', 'status', 'who', 'user']))));
}

$f = [
    'cert'   => (int)($_GET['cert'] ?? 0),
    'kind'   => (string)($_GET['kind'] ?? ''),
    'status' => (string)($_GET['status'] ?? ''),
    'who'    => (string)($_GET['who'] ?? ''),
    'user'   => (int)($_GET['user'] ?? 0),
];
$where = ['1 = 1'];
$args = [];
if ($f['cert']) { $where[] = 't.cert_id = ?'; $args[] = $f['cert']; }
if (in_array($f['kind'], ['practice', 'exam'], true)) { $where[] = 't.kind = ?'; $args[] = $f['kind']; }
if (in_array($f['status'], ['in_progress', 'done'], true)) { $where[] = 't.status = ?'; $args[] = $f['status']; }
if ($f['who'] === 'member') $where[] = 't.user_id IS NOT NULL';
if ($f['who'] === 'guest') $where[] = 't.user_id IS NULL';
if ($f['user']) { $where[] = 't.user_id = ?'; $args[] = $f['user']; }
$w = implode(' AND ', $where);
$total = (int)qv("SELECT COUNT(*) FROM tests t WHERE $w", $args);
$page = adminPage();
$rows = qall(
    "SELECT t.*, c.short_name, u.nickname FROM tests t JOIN certs c ON c.id = t.cert_id LEFT JOIN users u ON u.id = t.user_id
      WHERE $w ORDER BY t.id DESC LIMIT " . ADMIN_PER_PAGE . ' OFFSET ' . (($page - 1) * ADMIN_PER_PAGE),
    $args
);
$sel = fn(string $k, string $v) => $f[$k] === $v ? 'selected' : '';
adminHeader('시험 기록', 'tests');
?>
<h1>시험 기록 관리</h1>
<form class="admin-filter" method="get">
    <select name="cert"><option value="0">자격증 전체</option><?php foreach (adminCerts() as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $f['cert'] === (int)$c['id'] ? 'selected' : '' ?>><?= h($c['short_name']) ?></option><?php endforeach; ?></select>
    <select name="kind"><option value="">종류 전체</option><option value="practice" <?= $sel('kind', 'practice') ?>>연습</option><option value="exam" <?= $sel('kind', 'exam') ?>>실전</option></select>
    <select name="status"><option value="">상태 전체</option><option value="done" <?= $sel('status', 'done') ?>>완료</option><option value="in_progress" <?= $sel('status', 'in_progress') ?>>진행 중</option></select>
    <select name="who"><option value="">응시자 전체</option><option value="member" <?= $sel('who', 'member') ?>>회원</option><option value="guest" <?= $sel('who', 'guest') ?>>비회원</option></select>
    <?php if ($f['user']): ?><input type="hidden" name="user" value="<?= $f['user'] ?>"><span class="tag">회원 #<?= $f['user'] ?></span><?php endif; ?>
    <button class="btn small" type="submit">보기</button>
</form>
<div class="admin-actions">
    <?= adminPostButton('tests.php', ['action' => 'purge_guest'], '하루 지난 비회원 시험 정리', 'btn small', '하루 지난 비회원 시험 기록을 지울까?') ?>
    <?= adminPostButton('tests.php', ['action' => 'purge_stale'], '7일 넘게 방치된 진행 중 시험 정리', 'btn small', '7일 넘게 안 끝낸 시험을 지울까?') ?>
</div>
<section class="card glass">
    <table class="admin-table">
        <thead><tr><th>#</th><th>날짜</th><th>응시자</th><th>자격증</th><th>종류</th><th>난이도</th><th>상태</th><th>점수</th><th>정답</th><th>시간</th><th>일시정지</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $t): ?>
            <tr>
                <td><a href="test.php?id=<?= (int)$t['id'] ?>"><?= (int)$t['id'] ?></a></td>
                <td><?= h($t['test_date']) ?></td>
                <td><?= $t['user_id'] ? '<a href="user.php?id=' . (int)$t['user_id'] . '">' . h($t['nickname'] ?? '#' . $t['user_id']) . '</a>' : '<span class="muted">비회원</span>' ?></td>
                <td><?= h($t['short_name']) ?></td><td><?= $t['kind'] === 'exam' ? '실전' : '연습' ?></td>
                <td><?= diffLabel($t['difficulty'] === null ? null : (int)$t['difficulty']) ?></td>
                <td><?= $t['status'] === 'done' ? ((int)$t['passed'] ? '<span class="tag ok">합격</span>' : '<span class="tag bad">미달</span>') : '진행 중' ?></td>
                <td><?= $t['score'] === null ? '-' : (int)$t['score'] ?></td>
                <td><?= (int)$t['correct_cnt'] ?>/<?= (int)$t['total_cnt'] ?></td>
                <td><?= h(fmtDuration($t['duration_sec'] === null ? null : (int)$t['duration_sec'])) ?></td>
                <td><?= (int)$t['pause_count'] ?>회</td>
                <td><?= adminPostButton('tests.php?' . http_build_query(array_filter($f)), ['action' => 'delete', 'id' => (int)$t['id']], '삭제', 'btn small danger-btn', '이 시험 기록을 삭제할까?') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="12" class="muted">해당하는 시험이 없어.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <?= adminPager($total, $page, array_filter($f)) ?>
</section>
<?php adminFooter();
