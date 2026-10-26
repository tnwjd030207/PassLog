<?php
require __DIR__ . '/_init.php';
requireAdmin();

$id = (int)($_GET['id'] ?? 0);
$t = q1('SELECT t.*, c.name cert_name, u.nickname FROM tests t JOIN certs c ON c.id = t.cert_id LEFT JOIN users u ON u.id = t.user_id WHERE t.id = ?', [$id]);
if (!$t) { flash('시험을 찾을 수 없어.'); redirect('tests.php'); }
$items = qall(
    'SELECT ti.*, q.id qid, q.code, q.subject, q.topic, q.difficulty, q.question, q.choices, q.answer
       FROM test_items ti JOIN questions q ON q.id = ti.question_id WHERE ti.test_id = ? ORDER BY ti.position', [$id]
);
adminHeader('시험 #' . $id, 'tests');
?>
<p><a href="tests.php">← 시험 기록</a></p>
<h1>시험 #<?= $id ?> <small class="muted"><?= h($t['cert_name']) ?> · <?= $t['kind'] === 'exam' ? '실전 모의고사' : '연습' ?></small></h1>
<section class="card glass">
    <dl class="admin-dl">
        <dt>응시자</dt><dd><?= $t['user_id'] ? '<a href="user.php?id=' . (int)$t['user_id'] . '">' . h($t['nickname'] ?? '#' . $t['user_id']) . '</a>' : '비회원' ?></dd>
        <dt>상태 / 점수</dt><dd><?= $t['status'] === 'done' ? (int)$t['score'] . '점 (' . ((int)$t['passed'] ? '합격 기준 충족' : '미달') . ')' : '진행 중' ?></dd>
        <dt>정답</dt><dd><?= (int)$t['correct_cnt'] ?> / <?= (int)$t['total_cnt'] ?></dd>
        <dt>난이도</dt><dd><?= diffLabel($t['difficulty'] === null ? null : (int)$t['difficulty']) ?></dd>
        <dt>걸린 시간</dt><dd><?= h(fmtDuration($t['duration_sec'] === null ? null : (int)$t['duration_sec'])) ?></dd>
        <dt>문제별 제한</dt><dd><?= $t['q_seconds'] ? (int)$t['q_seconds'] . '초' : '없음' ?></dd>
        <dt>일시정지</dt><dd><?= (int)$t['pause_count'] ?>회 · <?= (int)$t['pause_total_sec'] ?>초</dd>
        <dt>시간 초과</dt><dd><?= (int)$t['timeouts'] ?>문제</dd>
        <dt>시작 / 제출</dt><dd><?= h((string)$t['started_at']) ?> / <?= h((string)$t['finished_at']) ?></dd>
    </dl>
</section>
<section class="card glass">
    <table class="admin-table">
        <thead><tr><th>번호</th><th>문제</th><th>과목·단원</th><th>정답</th><th>선택</th><th>결과</th><th>힌트</th><th>시간</th></tr></thead>
        <tbody>
        <?php foreach ($items as $it): ?>
            <tr>
                <td><?= (int)$it['position'] ?></td>
                <td><a href="question.php?id=<?= (int)$it['qid'] ?>"><?= h(mb_strimwidth((string)$it['question'], 0, 70, '…')) ?></a></td>
                <td><?= (int)$it['subject'] ?>과목 · <?= h($it['topic']) ?></td>
                <td><?= (int)$it['answer'] ?></td><td><?= $it['user_answer'] === null ? '-' : (int)$it['user_answer'] ?></td>
                <td><?= $it['is_correct'] === null ? '-' : ((int)$it['is_correct'] ? '<span class="tag ok">O</span>' : '<span class="tag bad">X</span>') ?><?= (int)$it['timed_out'] ? ' <span class="tag">시간초과</span>' : '' ?></td>
                <td><?= (int)$it['hint_used'] ? '사용' : '-' ?></td>
                <td><?= $it['spent_sec'] === null ? '-' : (int)$it['spent_sec'] . '초' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php adminFooter();
