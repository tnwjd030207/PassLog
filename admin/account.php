<?php
require __DIR__ . '/_init.php';
$me = requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'password') {
        $cur = (string)($_POST['current'] ?? '');
        $new = (string)($_POST['new'] ?? '');
        $hash = (string)qv('SELECT pass_hash FROM admins WHERE id = ?', [(int)$me['id']]);
        if (!password_verify($cur, $hash)) flash('지금 비밀번호가 맞지 않아.');
        elseif (strlen($new) < 10 || !preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) flash('새 비밀번호는 영문과 숫자를 섞어 10자 이상으로 해줘.');
        elseif ($new !== (string)($_POST['new2'] ?? '')) flash('새 비밀번호 확인이 달라.');
        else {
            q('UPDATE admins SET pass_hash = ?, pw_changed = 1 WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), (int)$me['id']]);
            flash('비밀번호를 바꿨어.');
        }
    } elseif ($action === 'add') {
        $u = trim((string)($_POST['username'] ?? ''));
        $p = (string)($_POST['password'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_]{4,30}$/', $u)) flash('관리자 아이디는 영문·숫자·_ 4~30자야.');
        elseif (strlen($p) < 10 || !preg_match('/[A-Za-z]/', $p) || !preg_match('/\d/', $p)) flash('비밀번호는 영문과 숫자를 섞어 10자 이상으로 해줘.');
        elseif (qv('SELECT 1 FROM admins WHERE username = ?', [$u])) flash('이미 있는 관리자 아이디야.');
        else {
            q('INSERT INTO admins (username, pass_hash, pw_changed) VALUES (?, ?, 1)', [$u, password_hash($p, PASSWORD_DEFAULT)]);
            flash('관리자 ' . $u . ' 를 추가했어.');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)$me['id']) flash('지금 로그인한 계정은 지울 수 없어.');
        else { q('DELETE FROM admins WHERE id = ?', [$id]); flash('관리자 계정을 삭제했어.'); }
    }
    redirect('account.php');
}
$admins = qall('SELECT id, username, created_at, last_login FROM admins ORDER BY id');
adminHeader('관리자 계정', 'account');
?>
<h1>관리자 계정</h1>
<div class="grid-2">
    <form method="post" class="card glass auth-form">
        <?= csrfField() ?><input type="hidden" name="action" value="password">
        <h2>내 비밀번호 바꾸기 <small class="muted"><?= h($me['username']) ?></small></h2>
        <label>지금 비밀번호<input type="password" name="current" required autocomplete="current-password"></label>
        <label>새 비밀번호 <small class="muted">영문+숫자 10자 이상</small><input type="password" name="new" required minlength="10" autocomplete="new-password"></label>
        <label>새 비밀번호 확인<input type="password" name="new2" required minlength="10" autocomplete="new-password"></label>
        <button class="btn primary" type="submit">변경</button>
    </form>
    <section class="card glass">
        <h2>관리자 목록</h2>
        <table class="admin-table">
            <thead><tr><th>아이디</th><th>만든 날</th><th>최근 로그인</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($admins as $a): ?>
                <tr><td><?= h($a['username']) ?></td><td><?= h(substr((string)$a['created_at'], 0, 10)) ?></td><td><?= h(substr((string)$a['last_login'], 0, 16)) ?></td>
                    <td><?= (int)$a['id'] === (int)$me['id'] ? '<span class="muted small">나</span>' : adminPostButton('account.php', ['action' => 'delete', 'id' => (int)$a['id']], '삭제', 'btn small danger-btn', '이 관리자 계정을 삭제할까?') ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <form method="post" class="auth-form">
            <?= csrfField() ?><input type="hidden" name="action" value="add">
            <h3>관리자 추가</h3>
            <label>아이디<input type="text" name="username" required maxlength="30" autocomplete="off"></label>
            <label>비밀번호<input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
            <button class="btn" type="submit">추가</button>
        </form>
    </section>
</div>
<?php adminFooter();
