<?php
require __DIR__ . '/_init.php';
requireAdmin();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$u = q1('SELECT * FROM users WHERE id = ?', [$id]);
if (!$u) { flash('회원을 찾을 수 없어.'); redirect('users.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'save') {
        $nick = trim((string)($_POST['nickname'] ?? ''));
        $plan = ($_POST['plan'] ?? 'free') === 'premium' ? 'premium' : 'free';
        $exp = trim((string)($_POST['plan_expires_at'] ?? ''));
        if ($nick !== '' && !validNickname($nick)) { flash(NICKNAME_RULE); redirect('user.php?id=' . $id); }
        if ($nick !== '' && nicknameTaken($nick, $id)) { flash('이미 누가 쓰고 있는 닉네임이야.'); redirect('user.php?id=' . $id); }
        if ($exp !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $exp)) { flash('만료일은 YYYY-MM-DD 형식으로 넣어줘.'); redirect('user.php?id=' . $id); }
        q('UPDATE users SET nickname = ?, plan = ?, plan_expires_at = ? WHERE id = ?',
            [$nick === '' ? null : $nick, $plan, $exp === '' ? null : $exp . ' 23:59:59', $id]);
        flash('회원 정보를 저장했어.');
        redirect('user.php?id=' . $id);
    }
    if ($action === 'reset_records') {
        q('DELETE FROM tests WHERE user_id = ?', [$id]);
        q('DELETE FROM lesson_progress WHERE user_id = ?', [$id]);
        flash('이 회원의 시험 기록과 강의 진도를 초기화했어.');
        redirect('user.php?id=' . $id);
    }
    if ($action === 'delete') {
        if (trim((string)($_POST['confirm'] ?? '')) !== '삭제') { flash("삭제하려면 확인 칸에 '삭제' 라고 입력해줘."); redirect('user.php?id=' . $id); }
        $pdo = pdo();
        $pdo->beginTransaction();
        q('DELETE FROM lesson_progress WHERE user_id = ?', [$id]);
        q('DELETE FROM user_certs WHERE user_id = ?', [$id]);
        q('DELETE FROM tests WHERE user_id = ?', [$id]);
        q('DELETE FROM users WHERE id = ?', [$id]);
        $pdo->commit();
        flash('회원 #' . $id . ' 과 모든 기록을 삭제했어.');
        redirect('users.php');
    }
}

$tests = qall(
    "SELECT t.*, c.short_name FROM tests t JOIN certs c ON c.id = t.cert_id WHERE t.user_id = ? ORDER BY t.id DESC LIMIT 50", [$id]
);
$progress = qall(
    "SELECT c.short_name, COUNT(*) total, SUM(lp.status = 'done') done
       FROM lesson_progress lp JOIN lessons l ON l.id = lp.lesson_id JOIN certs c ON c.id = l.cert_id
      WHERE lp.user_id = ? GROUP BY c.id ORDER BY c.sort", [$id]
);
$ucerts = qall('SELECT uc.*, c.short_name FROM user_certs uc JOIN certs c ON c.id = uc.cert_id WHERE uc.user_id = ?', [$id]);
adminHeader('회원 #' . $id, 'users');
?>
<p><a href="users.php">← 회원 목록</a></p>
<h1>회원 #<?= $id ?> <small class="muted"><?= h($u['nickname'] ?? '(닉네임 설정 전)') ?></small></h1>
<div class="grid-2">
    <section class="card glass">
        <h2>기본 정보</h2>
        <form method="post" class="auth-form">
            <?= csrfField() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $id ?>">
            <label>닉네임 <small class="muted">비우면 다음 접속 때 다시 정하게 됨</small><input type="text" name="nickname" value="<?= h($u['nickname'] ?? '') ?>" maxlength="10"></label>
            <label>요금제<select name="plan"><option value="free">free</option><option value="premium" <?= $u['plan'] === 'premium' ? 'selected' : '' ?>>premium</option></select></label>
            <label>유료 만료일 <small class="muted">(결제 연동 전 임시)</small><input type="date" name="plan_expires_at" value="<?= h(substr((string)$u['plan_expires_at'], 0, 10)) ?>"></label>
            <button class="btn primary" type="submit">저장</button>
        </form>
        <dl class="admin-dl">
            <dt>카카오 회원번호</dt><dd class="mono"><?= h($u['kakao_id'] ?? '-') ?></dd>
            <dt>내부 아이디</dt><dd class="mono"><?= h($u['username']) ?></dd>
            <dt>가입</dt><dd><?= h((string)$u['created_at']) ?></dd>
            <dt>최근 로그인</dt><dd><?= h((string)$u['last_login']) ?></dd>
        </dl>
    </section>
    <section class="card glass">
        <h2>학습 현황</h2>
        <table class="admin-table">
            <thead><tr><th>자격증</th><th>강의 완료 / 읽은 강의</th></tr></thead>
            <tbody>
            <?php foreach ($progress as $p): ?><tr><td><?= h($p['short_name']) ?></td><td><?= (int)$p['done'] ?> / <?= (int)$p['total'] ?></td></tr><?php endforeach; ?>
            <?php if (!$progress): ?><tr><td colspan="2" class="muted">강의 기록 없음</td></tr><?php endif; ?>
            </tbody>
        </table>
        <h3>시험일(D-day)</h3>
        <p class="muted small"><?php foreach ($ucerts as $uc) echo h($uc['short_name']) . ': ' . h((string)($uc['exam_date'] ?? '-')) . ' &nbsp; '; if (!$ucerts) echo '설정 없음'; ?></p>
        <h2 class="danger-h">위험 구역</h2>
        <?= adminPostButton('user.php', ['action' => 'reset_records', 'id' => $id], '시험 기록·강의 진도 초기화', 'btn small danger-btn', '이 회원의 시험 기록과 강의 진도를 모두 지울까? 되돌릴 수 없어.') ?>
        <form method="post" class="auth-form" onsubmit="return confirm('회원과 모든 기록을 삭제할까? 되돌릴 수 없어.');">
            <?= csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $id ?>">
            <label>회원 삭제 — 확인을 위해 <b>삭제</b> 입력<input type="text" name="confirm" autocomplete="off" placeholder="삭제"></label>
            <button class="btn danger-btn" type="submit">회원과 모든 기록 삭제</button>
        </form>
    </section>
</div>
<section class="card glass">
    <h2>시험 기록 <small class="muted">최근 50개</small></h2>
    <table class="admin-table">
        <thead><tr><th>#</th><th>날짜</th><th>자격증</th><th>종류</th><th>난이도</th><th>상태</th><th>점수</th><th>정답</th><th>일시정지</th></tr></thead>
        <tbody>
        <?php foreach ($tests as $t): ?>
            <tr><td><a href="test.php?id=<?= (int)$t['id'] ?>"><?= (int)$t['id'] ?></a></td><td><?= h($t['test_date']) ?></td><td><?= h($t['short_name']) ?></td>
                <td><?= $t['kind'] === 'exam' ? '실전' : '연습' ?></td><td><?= diffLabel($t['difficulty'] === null ? null : (int)$t['difficulty']) ?></td>
                <td><?= $t['status'] === 'done' ? ((int)$t['passed'] ? '<span class="tag ok">합격</span>' : '<span class="tag bad">미달</span>') : '진행 중' ?></td>
                <td><?= $t['score'] === null ? '-' : (int)$t['score'] ?></td><td><?= (int)$t['correct_cnt'] ?>/<?= (int)$t['total_cnt'] ?></td>
                <td><?= (int)$t['pause_count'] ?>회</td></tr>
        <?php endforeach; ?>
        <?php if (!$tests): ?><tr><td colspan="9" class="muted">시험 기록 없음</td></tr><?php endif; ?>
        </tbody>
    </table>
</section>
<?php adminFooter();
