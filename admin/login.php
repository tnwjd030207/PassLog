<?php
require __DIR__ . '/_init.php';

if (adminCurrent()) redirect('index.php');
$error = '';
$username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $key = 'admin:' . $username;
    if (tooManyAttempts($key)) {
        $error = '로그인 실패가 너무 많아. 10분 뒤에 다시 해줘.';
    } else {
        $a = q1('SELECT id, pass_hash FROM admins WHERE username = ?', [$username]);
        $hash = $a ? $a['pass_hash'] : '$2y$10$usesomesillystringforsalt.abcdefghijklmnopqrstuvwxyzABCDE';
        if (password_verify($password, $hash) && $a) {
            startSession();
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$a['id'];
            q('UPDATE admins SET last_login = NOW() WHERE id = ?', [(int)$a['id']]);
            redirect('index.php');
        }
        recordFailedAttempt($key);
        $error = '아이디 또는 비밀번호가 맞지 않아.';
    }
}
adminHeader('로그인', '');
?>
<section class="card glass auth-card">
    <h1>관리자 로그인</h1>
    <?php if ($error): ?><p class="auth-err" role="alert"><?= h($error) ?></p><?php endif; ?>
    <form method="post" class="auth-form" autocomplete="off">
        <?= csrfField() ?>
        <label>아이디<input type="text" name="username" value="<?= h($username) ?>" required autofocus></label>
        <label>비밀번호<input type="password" name="password" required autocomplete="current-password"></label>
        <button class="btn primary" type="submit">로그인</button>
    </form>
</section>
<?php adminFooter();
