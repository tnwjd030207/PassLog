<?php
require __DIR__ . '/_init.php';
requireAdmin();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$c = $id ? q1('SELECT * FROM certs WHERE id = ?', [$id]) : null;
if ($id && !$c) { flash('자격증을 찾을 수 없어.'); redirect('certs.php'); }
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? 'save');
    if ($action === 'save') {
        $d = [
            'code' => $c ? (string)$c['code'] : strtolower(trim((string)($_POST['code'] ?? ''))),   // 기존 자격증 코드는 고정 (콘텐츠 파일과 연결된 키)
            'name' => trim((string)($_POST['name'] ?? '')),
            'short_name' => trim((string)($_POST['short_name'] ?? '')), 'org' => trim((string)($_POST['org'] ?? '')),
            'description' => trim((string)($_POST['description'] ?? '')), 'exam_note' => trim((string)($_POST['exam_note'] ?? '')),
            'minutes' => (int)($_POST['minutes'] ?? 0), 'pass_total' => (int)($_POST['pass_total'] ?? 60),
            'pass_subject' => ($_POST['pass_subject'] ?? '') === '' ? null : (int)$_POST['pass_subject'],
            'color' => (string)($_POST['color'] ?? '#5eead4'), 'sort' => (int)($_POST['sort'] ?? 0), 'active' => isset($_POST['active']) ? 1 : 0,
        ];
        if (!preg_match('/^[a-z0-9]{2,20}$/', $d['code'])) $error = '코드는 영문 소문자·숫자 2~20자로 넣어줘.';
        elseif ($d['name'] === '' || $d['short_name'] === '') $error = '이름과 짧은 이름을 넣어줘.';
        elseif ($d['minutes'] < 1 || $d['minutes'] > 600) $error = '시험 시간(분)을 확인해줘.';
        elseif ($d['pass_total'] < 1 || $d['pass_total'] > 100) $error = '합격 총점은 1~100 사이야.';
        elseif ($d['pass_subject'] !== null && ($d['pass_subject'] < 1 || $d['pass_subject'] > 100)) $error = '과락 기준은 1~100 사이거나 비워둬.';
        elseif (!preg_match('/^#[0-9a-fA-F]{6}$/', $d['color'])) $error = '색은 #RRGGBB 형식이야.';
        elseif (qv('SELECT 1 FROM certs WHERE code = ? AND id <> ?', [$d['code'], $id])) $error = '이미 있는 코드야.';
        if ($error === '') {
            $vals = [$d['code'], $d['name'], $d['short_name'], $d['org'], $d['description'], $d['exam_note'], $d['minutes'], $d['pass_total'], $d['pass_subject'], $d['color'], $d['sort'], $d['active']];
            if ($c) {
                q('UPDATE certs SET code = ?, name = ?, short_name = ?, org = ?, description = ?, exam_note = ?, minutes = ?, pass_total = ?, pass_subject = ?, color = ?, sort = ?, active = ?, admin_edited = 1 WHERE id = ?', array_merge($vals, [$id]));
            } else {
                q('INSERT INTO certs (code, name, short_name, org, description, exam_note, minutes, pass_total, pass_subject, color, sort, active, admin_edited) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)', $vals);
                $id = (int)pdo()->lastInsertId();
                q('INSERT INTO cert_subjects (cert_id, no, name, exam_count) VALUES (?, 1, ?, 20)', [$id, '1과목']);
            }
            flash('자격증 정보를 저장했어.');
            redirect('cert.php?id=' . $id);
        }
        $c = array_merge($c ?? [], $d);
    } elseif ($c && $action === 'subjects') {
        foreach ((array)($_POST['subj'] ?? []) as $no => $s) {
            $name = trim((string)($s['name'] ?? ''));
            $cnt = max(0, (int)($s['count'] ?? 0));
            if ($name === '') continue;
            q('UPDATE cert_subjects SET name = ?, exam_count = ? WHERE cert_id = ? AND no = ?', [$name, $cnt, $id, (int)$no]);
        }
        $newName = trim((string)($_POST['new_subject'] ?? ''));
        if ($newName !== '') {
            $next = (int)qv('SELECT COALESCE(MAX(no), 0) + 1 FROM cert_subjects WHERE cert_id = ?', [$id]);
            q('INSERT INTO cert_subjects (cert_id, no, name, exam_count) VALUES (?, ?, ?, ?)', [$id, $next, $newName, max(0, (int)($_POST['new_count'] ?? 0))]);
        }
        q('UPDATE certs SET admin_edited = 1 WHERE id = ?', [$id]);
        flash('과목 설정을 저장했어.');
        redirect('cert.php?id=' . $id);
    } elseif ($c && $action === 'topics') {
        foreach ((array)($_POST['tip'] ?? []) as $tid => $tip) {
            q('UPDATE topics SET tip = ?, sort = ?, admin_edited = 1 WHERE id = ? AND cert_id = ?',
                [mb_substr(trim((string)$tip), 0, 500), (int)($_POST['tsort'][$tid] ?? 0), (int)$tid, $id]);
        }
        flash('단원 공부 팁을 저장했어.');
        redirect('cert.php?id=' . $id);
    }
}

$c ??= ['code' => '', 'name' => '', 'short_name' => '', 'org' => '', 'description' => '', 'exam_note' => '', 'minutes' => 90, 'pass_total' => 60, 'pass_subject' => 40, 'color' => '#5eead4', 'sort' => 99, 'active' => 1];
$subjects = $id ? qall('SELECT s.*, (SELECT COUNT(*) FROM questions q WHERE q.cert_id = s.cert_id AND q.subject = s.no AND q.active = 1) qn FROM cert_subjects s WHERE s.cert_id = ? ORDER BY s.no', [$id]) : [];
$topics = $id ? qall('SELECT t.*, (SELECT COUNT(*) FROM questions q WHERE q.cert_id = t.cert_id AND q.topic = t.name AND q.active = 1) qn FROM topics t WHERE t.cert_id = ? ORDER BY t.subject, t.sort, t.id', [$id]) : [];
adminHeader($id ? '자격증 설정' : '새 자격증', 'certs');
?>
<p><a href="certs.php">← 자격증 목록</a></p>
<h1><?= $id ? h($c['name']) . ' 설정' : '새 자격증' ?></h1>
<?php if ($error): ?><p class="auth-err" role="alert"><?= h($error) ?></p><?php endif; ?>
<form method="post" class="card glass admin-form">
    <?= csrfField() ?><input type="hidden" name="action" value="save"><?php if ($id): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
    <div class="admin-row">
        <label>코드 <small class="muted"><?= $id ? '바꿀 수 없음' : 'URL용 영문 소문자' ?></small><input type="text" name="code" value="<?= h($c['code']) ?>" required maxlength="20" <?= $id ? 'readonly' : '' ?>></label>
        <label>이름<input type="text" name="name" value="<?= h($c['name']) ?>" required maxlength="60"></label>
        <label>짧은 이름<input type="text" name="short_name" value="<?= h($c['short_name']) ?>" required maxlength="20"></label>
        <label>시행 기관<input type="text" name="org" value="<?= h($c['org']) ?>" maxlength="60"></label>
    </div>
    <label>설명<input type="text" name="description" value="<?= h($c['description']) ?>" maxlength="255"></label>
    <label>시험 구성 안내 문구<input type="text" name="exam_note" value="<?= h($c['exam_note']) ?>" maxlength="255"></label>
    <div class="admin-row">
        <label>시험 시간(분)<input type="number" name="minutes" value="<?= (int)$c['minutes'] ?>" min="1" max="600"></label>
        <label>합격 총점<input type="number" name="pass_total" value="<?= (int)$c['pass_total'] ?>" min="1" max="100"></label>
        <label>과목 과락 기준(%) <small class="muted">비우면 과락 없음</small><input type="number" name="pass_subject" value="<?= $c['pass_subject'] === null ? '' : (int)$c['pass_subject'] ?>" min="1" max="100"></label>
        <label>색<input type="color" name="color" value="<?= h($c['color']) ?>"></label>
        <label>표시 순서<input type="number" name="sort" value="<?= (int)$c['sort'] ?>" min="0" max="127"></label>
    </div>
    <label class="check"><input type="checkbox" name="active" <?= (int)$c['active'] ? 'checked' : '' ?>> 사이트에 공개</label>
    <button class="btn primary" type="submit">저장</button>
</form>

<?php if ($id): ?>
<form method="post" class="card glass admin-form">
    <?= csrfField() ?><input type="hidden" name="action" value="subjects"><input type="hidden" name="id" value="<?= $id ?>">
    <h2>과목 · 실전 문항 수</h2>
    <p class="muted small">실전 모의고사는 과목별로 여기 적힌 문항 수만큼 출제돼. 문제별 제한 시간 = 시험 시간 ÷ 전체 문항 수.</p>
    <table class="admin-table">
        <thead><tr><th>번호</th><th>과목 이름</th><th>실전 문항 수</th><th>공개 문제</th></tr></thead>
        <tbody>
        <?php foreach ($subjects as $s): ?>
            <tr><td><?= (int)$s['no'] ?></td>
                <td><input type="text" name="subj[<?= (int)$s['no'] ?>][name]" value="<?= h($s['name']) ?>" maxlength="60"></td>
                <td><input type="number" name="subj[<?= (int)$s['no'] ?>][count]" value="<?= (int)$s['exam_count'] ?>" min="0" max="200"></td>
                <td><?= (int)$s['qn'] ?><?= (int)$s['qn'] < (int)$s['exam_count'] ? ' <span class="tag bad">부족</span>' : '' ?></td></tr>
        <?php endforeach; ?>
            <tr><td>+</td><td><input type="text" name="new_subject" placeholder="새 과목 추가 (선택)" maxlength="60"></td><td><input type="number" name="new_count" value="0" min="0" max="200"></td><td></td></tr>
        </tbody>
    </table>
    <button class="btn primary" type="submit">과목 저장</button>
</form>

<form method="post" class="card glass admin-form">
    <?= csrfField() ?><input type="hidden" name="action" value="topics"><input type="hidden" name="id" value="<?= $id ?>">
    <h2>단원 · 공부 팁</h2>
    <p class="muted small">약점 분석과 결과표에서 이 단원이 약할 때 보여주는 공부법이야.</p>
    <table class="admin-table">
        <thead><tr><th>과목</th><th>단원</th><th>순서</th><th>공개 문제</th><th>공부 팁</th></tr></thead>
        <tbody>
        <?php foreach ($topics as $t): ?>
            <tr><td><?= (int)$t['subject'] ?></td><td><?= h($t['name']) ?></td>
                <td><input type="number" name="tsort[<?= (int)$t['id'] ?>]" value="<?= (int)$t['sort'] ?>" min="0" max="999" style="width:5em"></td>
                <td><?= (int)$t['qn'] ?></td>
                <td><textarea name="tip[<?= (int)$t['id'] ?>]" rows="2" maxlength="500"><?= h($t['tip']) ?></textarea></td></tr>
        <?php endforeach; ?>
        <?php if (!$topics): ?><tr><td colspan="5" class="muted">단원이 없어. 문제나 강의를 추가하면 자동으로 생겨.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <button class="btn primary" type="submit">팁 저장</button>
</form>
<?php endif; ?>
<?php adminFooter();
