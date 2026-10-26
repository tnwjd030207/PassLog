<?php
require __DIR__ . '/_init.php';
require __DIR__ . '/../includes/markdown.php';
requireAdmin();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$l = $id ? q1('SELECT * FROM lessons WHERE id = ?', [$id]) : null;
if ($id && !$l) { flash('강의를 찾을 수 없어.'); redirect('lessons.php'); }
$certs = adminCerts();
$error = '';
$form = $l ?: ['cert_id' => (int)($_GET['cert'] ?? ($certs[0]['id'] ?? 0)), 'subject' => 1, 'topic' => '', 'sort' => 99, 'title' => '', 'minutes' => 10, 'body' => '', 'active' => 1];
$preview = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = [
        'cert_id' => (int)($_POST['cert_id'] ?? 0), 'subject' => (int)($_POST['subject'] ?? 1), 'topic' => trim((string)($_POST['topic'] ?? '')),
        'sort' => (int)($_POST['sort'] ?? 0), 'title' => trim((string)($_POST['title'] ?? '')), 'minutes' => max(1, (int)($_POST['minutes'] ?? 10)),
        'body' => str_replace("\r\n", "\n", (string)($_POST['body'] ?? '')), 'active' => isset($_POST['active']) ? 1 : 0,
    ];
    $preview = ($_POST['do'] ?? '') === 'preview';
    if (!qv('SELECT 1 FROM certs WHERE id = ?', [$form['cert_id']])) $error = '자격증을 골라줘.';
    elseif (!qv('SELECT 1 FROM cert_subjects WHERE cert_id = ? AND no = ?', [$form['cert_id'], $form['subject']])) $error = '과목 번호가 이 자격증에 없어.';
    elseif ($form['topic'] === '' || mb_strlen($form['topic']) > 40) $error = '단원 이름을 1~40자로 넣어줘.';
    elseif ($form['title'] === '' || mb_strlen($form['title']) > 150) $error = '제목을 1~150자로 넣어줘.';
    elseif (trim($form['body']) === '') $error = '본문을 넣어줘.';

    if ($error === '' && !$preview) {
        $vals = [$form['cert_id'], $form['subject'], $form['topic'], $form['sort'], $form['title'], $form['minutes'], $form['body'], $form['active']];
        if ($l) {
            q('UPDATE lessons SET cert_id = ?, subject = ?, topic = ?, sort = ?, title = ?, minutes = ?, body = ?, active = ?, admin_edited = 1 WHERE id = ?', array_merge($vals, [$id]));
            flash('강의를 저장했어.');
        } else {
            $code = 'ADM-L-' . substr(bin2hex(random_bytes(5)), 0, 9);
            q('INSERT INTO lessons (cert_id, subject, topic, sort, title, minutes, body, active, code, admin_edited) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)', array_merge($vals, [$code]));
            $id = (int)pdo()->lastInsertId();
            q('INSERT IGNORE INTO topics (cert_id, subject, name, tip, sort, admin_edited) VALUES (?, ?, ?, ?, 999, 1)', [$form['cert_id'], $form['subject'], $form['topic'], '']);
            flash('새 강의를 추가했어.');
        }
        redirect('lesson.php?id=' . $id);
    }
}
$subjects = qall('SELECT cert_id, no, name FROM cert_subjects ORDER BY cert_id, no');
$certTopics = qall('SELECT cert_id, name FROM topics ORDER BY cert_id, subject, sort');
adminHeader($l ? '강의 수정' : '새 강의', 'lessons');
?>
<p><a href="lessons.php?cert=<?= (int)$form['cert_id'] ?>">← 강의 목록</a><?php if ($l): ?> · <a href="../lesson.php?id=<?= $id ?>" target="_blank" rel="noopener">사이트에서 보기</a><?php endif; ?></p>
<h1><?= $l ? '강의 수정 <small class="muted mono">' . h($l['code']) . '</small>' : '새 강의' ?></h1>
<?php if ($error): ?><p class="auth-err" role="alert"><?= h($error) ?></p><?php endif; ?>
<form method="post" class="card glass admin-form">
    <?= csrfField() ?><?php if ($l): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
    <div class="admin-row">
        <label>자격증<select name="cert_id" id="cert_id"><?php foreach ($certs as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$form['cert_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?></select></label>
        <label>과목<select name="subject" id="subject"><?php foreach ($subjects as $s): ?><option value="<?= (int)$s['no'] ?>" data-cert="<?= (int)$s['cert_id'] ?>" <?= (int)$form['subject'] === (int)$s['no'] && (int)$form['cert_id'] === (int)$s['cert_id'] ? 'selected' : '' ?>><?= (int)$s['no'] ?>. <?= h($s['name']) ?></option><?php endforeach; ?></select></label>
        <label>단원<input type="text" name="topic" list="topic-list" value="<?= h($form['topic']) ?>" maxlength="40" required></label>
        <label>순서<input type="number" name="sort" value="<?= (int)$form['sort'] ?>" min="0" max="999"></label>
        <label>예상 분량(분)<input type="number" name="minutes" value="<?= (int)$form['minutes'] ?>" min="1" max="120"></label>
    </div>
    <datalist id="topic-list"><?php foreach ($certTopics as $t): ?><option value="<?= h($t['name']) ?>" data-cert="<?= (int)$t['cert_id'] ?>"></option><?php endforeach; ?></datalist>
    <label>제목<input type="text" name="title" value="<?= h($form['title']) ?>" maxlength="150" required></label>
    <label>본문 <small class="muted">마크다운: ## 제목, - 목록, **굵게**, `코드`, ``` 코드 블록 ```, &gt; 팁 상자, 표(| a | b |)</small>
        <textarea name="body" rows="22" class="mono"><?= h($form['body']) ?></textarea></label>
    <label class="check"><input type="checkbox" name="active" <?= (int)$form['active'] ? 'checked' : '' ?>> 공개</label>
    <div class="admin-row"><button class="btn" type="submit" name="do" value="preview">미리보기</button><button class="btn primary" type="submit" name="do" value="save">저장</button></div>
</form>
<?php if ($preview && $error === ''): $toc = []; ?>
<section class="card glass"><h2>미리보기 <small class="muted">아직 저장 안 됨</small></h2><div class="md"><?= markdown($form['body'], $toc) ?></div></section>
<?php endif; ?>
<script>
(function () {
  var cert = document.getElementById('cert_id'), subj = document.getElementById('subject');
  function sync() {
    var v = cert.value, firstOk = null;
    Array.prototype.forEach.call(subj.options, function (o) { var ok = o.dataset.cert === v; o.hidden = !ok; o.disabled = !ok; if (ok && !firstOk) firstOk = o; });
    if (subj.selectedOptions[0] && subj.selectedOptions[0].disabled && firstOk) firstOk.selected = true;
  }
  cert.addEventListener('change', sync); sync();
})();
</script>
<?php adminFooter();
