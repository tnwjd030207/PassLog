<?php
// 닉네임 정하기/바꾸기. 닉네임이 없는 회원(예전 가입자)은 로그인하면 이 화면으로 먼저 온다.
declare(strict_types=1);
define('NICKNAME_PAGE', true);
require __DIR__ . '/includes/functions.php';

$me = currentUser();
$first = ($me['nickname'] ?? '') === '';
$error = '';
$nickname = (string)($me['nickname'] ?? '');
$suggest = $first ? (string)($_SESSION['nick_suggest'] ?? '') : '';
if ($first && $nickname === '') $nickname = $suggest;   // 카카오 닉네임을 미리 채워 둠 (그대로 써도, 바꿔도 됨)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nickname = trim((string)($_POST['nickname'] ?? ''));
    if (!validNickname($nickname)) {
        $error = NICKNAME_RULE;
    } elseif (nicknameTaken($nickname, (int)$me['id'])) {
        $error = '이미 누가 쓰고 있는 닉네임이야.';
    } else {
        try {
            q('UPDATE users SET nickname = ? WHERE id = ?', [$nickname, (int)$me['id']]);
            $after = $first ? safeNext($_SESSION['nick_next'] ?? '') : 'index.php';
            unset($_SESSION['nick_suggest'], $_SESSION['nick_next']);
            if ($first) flash('가입 완료! ' . $nickname . '님, 환영해.');
            redirect($after);
        } catch (PDOException $e) {   // 동시에 같은 닉네임을 잡은 경우
            $error = '이미 누가 쓰고 있는 닉네임이야.';
        }
    }
}

$pageTitle = '닉네임';
$showCert = false;
require __DIR__ . '/includes/header.php';
?>
<section class="card glass auth-card">
    <h1><?= $first ? '마지막 단계: 닉네임 정하기' : '닉네임 바꾸기' ?></h1>
    <p class="muted"><?= $first ? ($suggest !== '' ? 'PassLog 에서 쓸 이름이야. 카카오 닉네임을 미리 넣어 뒀으니 그대로 써도 되고, 원하는 이름으로 바꿔도 돼.' : 'PassLog 에서 쓸 이름을 한글로 정해줘. 나중에 언제든 바꿀 수 있어.') : '카카오 로그인은 그대로고, 화면에 보이는 이름만 바뀌어.' ?></p>
    <?php if ($error): ?><p class="auth-err" role="alert"><?= h($error) ?></p><?php endif; ?>
    <form method="post" class="auth-form">
        <label>닉네임 <small class="muted">한글 2~10자</small>
            <input type="text" name="nickname" value="<?= h($nickname) ?>" required minlength="2" maxlength="10"
                   pattern="[가-힣]{2,10}" title="<?= h(NICKNAME_RULE) ?>" autocomplete="nickname" autofocus>
        </label>
        <button class="btn primary" type="submit">저장</button>
    </form>
    <?php if (!$first): ?><p class="muted"><a href="index.php">대시보드로 돌아가기</a></p><?php endif; ?>
</section>
<section class="card glass auth-card withdraw-card">
    <h2>회원 탈퇴</h2>
    <p class="muted">내 계정과 시험 기록, 오답·분석 기록, 강의 진도가 모두 지워지고 되돌릴 수 없어. 문제은행과 강의는 그대로 남아.</p>
    <form method="post" action="withdraw.php" class="auth-form" onsubmit="return confirm('정말 탈퇴할까? 기록이 모두 지워지고 되돌릴 수 없어.');">
        <label>확인을 위해 <b>탈퇴</b> 라고 입력해줘
            <input type="text" name="confirm" required autocomplete="off" placeholder="탈퇴">
        </label>
        <button class="btn danger-btn" type="submit">회원 탈퇴</button>
    </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
