<?php
require __DIR__ . '/_init.php';
requireAdmin();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$q = $id ? q1('SELECT * FROM questions WHERE id = ?', [$id]) : null;
if ($id && !$q) { flash('문제를 찾을 수 없어.'); redirect('questions.php'); }
$certs = adminCerts();
$error = '';

$form = $q ? array_merge($q, ['choices' => json_decode((string)$q['choices'], true) ?: ['', '', '', '']]) : [
    'cert_id' => (int)($_GET['cert'] ?? ($certs[0]['id'] ?? 0)), 'subject' => 1, 'topic' => '', 'difficulty' => 2, 'question' => '',
    'code_block' => '', 'choices' => ['', '', '', ''], 'answer' => 1, 'hint' => '', 'explanation' => '', 'mono' => 0, 'active' => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = [
        'cert_id' => (int)($_POST['cert_id'] ?? 0), 'subject' => (int)($_POST['subject'] ?? 1), 'topic' => trim((string)($_POST['topic'] ?? '')),
        'difficulty' => (int)($_POST['difficulty'] ?? 2), 'question' => trim((string)($_POST['question'] ?? '')),
        'code_block' => rtrim((string)($_POST['code_block'] ?? '')), 'choices' => array_map(fn($c) => trim((string)$c), array_slice((array)($_POST['choices'] ?? []), 0, 4)),
        'answer' => (int)($_POST['answer'] ?? 0), 'hint' => trim((string)($_POST['hint'] ?? '')), 'explanation' => trim((string)($_POST['explanation'] ?? '')),
        'mono' => isset($_POST['mono']) ? 1 : 0, 'active' => isset($_POST['active']) ? 1 : 0,
    ];
    $form['choices'] = array_pad($form['choices'], 4, '');
    $subjCount = (int)qv('SELECT COUNT(*) FROM cert_subjects WHERE cert_id = ?', [$form['cert_id']]);
    if (!qv('SELECT 1 FROM certs WHERE id = ?', [$form['cert_id']])) $error = '자격증을 골라줘.';
    elseif ($form['subject'] < 1 || $form['subject'] > max(1, $subjCount)) $error = '과목 번호가 이 자격증에 없어.';
    elseif ($form['topic'] === '' || mb_strlen($form['topic']) > 40) $error = '단원 이름을 1~40자로 넣어줘.';
    elseif (!in_array($form['difficulty'], [1, 2, 3], true)) $error = '난이도를 골라줘.';
    elseif ($form['question'] === '') $error = '문제 내용을 넣어줘.';
    elseif (in_array('', $form['choices'], true)) $error = '보기 4개를 모두 채워줘.';
    elseif (count(array_unique($form['choices'])) < 4) $error = '보기가 서로 달라야 해.';
    elseif ($form['answer'] < 1 || $form['answer'] > 4) $error = '정답 번호를 골라줘.';
    elseif ($form['hint'] === '' || $form['explanation'] === '') $error = '힌트와 해설을 넣어줘.';

    if ($error === '') {
        $vals = [$form['cert_id'], $form['subject'], $form['topic'], $form['difficulty'], $form['question'],
            $form['code_block'] === '' ? null : $form['code_block'], json_encode($form['choices'], JSON_UNESCAPED_UNICODE),
            $form['answer'], $form['hint'], $form['explanation'], $form['mono'], $form['active']];
        if ($q) {
            q('UPDATE questions SET cert_id = ?, subject = ?, topic = ?, difficulty = ?, question = ?, code_block = ?, choices = ?,
                 answer = ?, hint = ?, explanation = ?, mono = ?, active = ?, admin_edited = 1 WHERE id = ?', array_merge($vals, [$id]));
            flash('문제를 저장했어.');
        } else {
            $code = 'ADM-' . strtoupper(substr((string)qv('SELECT code FROM certs WHERE id = ?', [$form['cert_id']]), 0, 6)) . '-' . substr(bin2hex(random_bytes(4)), 0, 7);
            q('INSERT INTO questions (cert_id, subject, topic, difficulty, question, code_block, choices, answer, hint, explanation, mono, active, code, admin_edited)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)', array_merge($vals, [$code]));
            $id = (int)pdo()->lastInsertId();
            // 단원이 새로 생겼으면 단원 목록에도 추가
            q('INSERT IGNORE INTO topics (cert_id, subject, name, tip, sort, admin_edited) VALUES (?, ?, ?, ?, 999, 1)', [$form['cert_id'], $form['subject'], $form['topic'], '']);
            flash('새 문제를 추가했어. (코드 ' . $code . ')');
        }
        redirect('question.php?id=' . $id);
    }
}

$certTopics = qall('SELECT cert_id, subject, name FROM topics ORDER BY cert_id, subject, sort');
$subjects = qall('SELECT cert_id, no, name FROM cert_subjects ORDER BY cert_id, no');
$usage = $q ? q1('SELECT COUNT(*) n, ROUND(AVG(is_correct) * 100) acc FROM test_items WHERE question_id = ? AND is_correct IS NOT NULL', [$id]) : null;
adminHeader($q ? '문제 수정' : '새 문제', 'questions');
?>
<p><a href="questions.php?cert=<?= (int)$form['cert_id'] ?>">← 문제 목록</a></p>
<h1><?= $q ? '문제 수정 <small class="muted mono">' . h($q['code']) . '</small>' : '새 문제' ?></h1>
<?php if ($usage): ?><p class="muted">지금까지 <?= (int)$usage['n'] ?>번 풀림 · 정답률 <?= $usage['acc'] === null ? '-' : (int)$usage['acc'] . '%' ?><?= (int)$q['admin_edited'] ? ' · 관리자 수정본(설치 파일 재실행해도 유지)' : '' ?></p><?php endif; ?>
<?php if ($error): ?><p class="auth-err" role="alert"><?= h($error) ?></p><?php endif; ?>
<form method="post" class="card glass admin-form">
    <?= csrfField() ?><?php if ($q): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
    <div class="admin-row">
        <label>자격증<select name="cert_id" id="cert_id"><?php foreach ($certs as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$form['cert_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?></select></label>
        <label>과목<select name="subject" id="subject"><?php foreach ($subjects as $s): ?><option value="<?= (int)$s['no'] ?>" data-cert="<?= (int)$s['cert_id'] ?>" <?= (int)$form['subject'] === (int)$s['no'] && (int)$form['cert_id'] === (int)$s['cert_id'] ? 'selected' : '' ?>><?= (int)$s['no'] ?>. <?= h($s['name']) ?></option><?php endforeach; ?></select></label>
        <label>단원 <small class="muted">목록에 없으면 새로 입력</small><input type="text" name="topic" list="topic-list" value="<?= h($form['topic']) ?>" maxlength="40" required></label>
        <label>난이도<select name="difficulty"><option value="1" <?= (int)$form['difficulty'] === 1 ? 'selected' : '' ?>>하</option><option value="2" <?= (int)$form['difficulty'] === 2 ? 'selected' : '' ?>>중</option><option value="3" <?= (int)$form['difficulty'] === 3 ? 'selected' : '' ?>>상</option></select></label>
    </div>
    <datalist id="topic-list"><?php foreach ($certTopics as $t): ?><option value="<?= h($t['name']) ?>" data-cert="<?= (int)$t['cert_id'] ?>"></option><?php endforeach; ?></datalist>
    <label>문제<textarea name="question" rows="3" required><?= h($form['question']) ?></textarea></label>
    <label>코드/표 블록 <small class="muted">(선택, 문제 아래 고정폭 글꼴로 표시)</small><textarea name="code_block" rows="5" class="mono"><?= h((string)$form['code_block']) ?></textarea></label>
    <fieldset class="admin-choices"><legend>보기 (정답을 선택)</legend>
        <?php for ($i = 0; $i < 4; $i++): ?>
            <label class="choice-row"><input type="radio" name="answer" value="<?= $i + 1 ?>" <?= (int)$form['answer'] === $i + 1 ? 'checked' : '' ?>> <?= $i + 1 ?>
                <input type="text" name="choices[]" value="<?= h($form['choices'][$i] ?? '') ?>" required></label>
        <?php endfor; ?>
    </fieldset>
    <label>힌트 <small class="muted">정답을 바로 알려주지 않게</small><textarea name="hint" rows="2" required><?= h($form['hint']) ?></textarea></label>
    <label>해설<textarea name="explanation" rows="4" required><?= h($form['explanation']) ?></textarea></label>
    <div class="admin-row">
        <label class="check"><input type="checkbox" name="mono" <?= (int)$form['mono'] ? 'checked' : '' ?>> 보기를 코드 글꼴로</label>
        <label class="check"><input type="checkbox" name="active" <?= (int)$form['active'] ? 'checked' : '' ?>> 공개 (문제 풀이에 출제)</label>
    </div>
    <button class="btn primary" type="submit">저장</button>
</form>
<script>
// 자격증을 바꾸면 그 자격증의 과목·단원만 보이게
(function () {
  var cert = document.getElementById('cert_id'), subj = document.getElementById('subject');
  function sync() {
    var v = cert.value, firstOk = null;
    Array.prototype.forEach.call(subj.options, function (o) { var ok = o.dataset.cert === v; o.hidden = !ok; o.disabled = !ok; if (ok && !firstOk) firstOk = o; });
    if (subj.selectedOptions[0] && subj.selectedOptions[0].disabled && firstOk) firstOk.selected = true;
    Array.prototype.forEach.call(document.querySelectorAll('#topic-list option'), function (o) { o.disabled = o.dataset.cert !== v; });
  }
  cert.addEventListener('change', sync); sync();
})();
</script>
<?php adminFooter();
