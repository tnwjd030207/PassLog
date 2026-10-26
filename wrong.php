<?php
declare(strict_types=1);
require __DIR__ . '/includes/functions.php';

$cert = currentCert();
$subject = (int)($_GET['subject'] ?? 0);
$topic = (string)($_GET['topic'] ?? '');
$onlyNow = ($_GET['view'] ?? 'now') === 'now';   // now: 마지막에 틀린 문제만 / all: 한 번이라도 틀린 문제
$u = uid();

$sql = "SELECT q.id, q.code, q.subject, q.topic, q.difficulty, q.question, q.code_block, q.choices, q.answer, q.explanation, q.mono,
               COUNT(ti.id) AS tries, SUM(ti.is_correct = 0) AS wrong_n,
               (SELECT x.is_correct FROM test_items x JOIN tests tx ON tx.id = x.test_id
                 WHERE x.question_id = q.id AND tx.status = 'done' AND tx.user_id = ? ORDER BY tx.id DESC, x.id DESC LIMIT 1) AS last_ok,
               (SELECT x.user_answer FROM test_items x JOIN tests tx ON tx.id = x.test_id
                 WHERE x.question_id = q.id AND tx.status = 'done' AND tx.user_id = ? ORDER BY tx.id DESC, x.id DESC LIMIT 1) AS last_ans,
               (SELECT MAX(tx.test_date) FROM test_items x JOIN tests tx ON tx.id = x.test_id
                 WHERE x.question_id = q.id AND tx.status = 'done' AND tx.user_id = ?) AS last_date
          FROM questions q
          JOIN test_items ti ON ti.question_id = q.id
          JOIN tests te ON te.id = ti.test_id AND te.status = 'done' AND te.user_id = ?
         WHERE q.cert_id = ?";
$args = [$u, $u, $u, $u, $cert['id']];
if (isset($cert['subjects'][$subject])) { $sql .= ' AND q.subject = ?'; $args[] = $subject; } else { $subject = 0; }
if ($topic !== '') { $sql .= ' AND q.topic = ?'; $args[] = $topic; }
$sql .= ' GROUP BY q.id ' . ($onlyNow ? 'HAVING last_ok = 0' : 'HAVING wrong_n > 0') . ' ORDER BY last_date DESC, q.id';
$rows = qall($sql, $args);

$topicList = qall("SELECT DISTINCT q.subject, q.topic FROM questions q JOIN test_items ti ON ti.question_id = q.id
                     JOIN tests te ON te.id = ti.test_id AND te.status = 'done' AND te.user_id = ?
                    WHERE ti.is_correct = 0 AND q.cert_id = ? ORDER BY q.subject, q.topic", [$u, $cert['id']]);

function wrongUrl(array $over): string
{
    global $subject, $topic, $onlyNow;
    $q = array_merge(['subject' => $subject ?: null, 'topic' => $topic ?: null, 'view' => $onlyNow ? 'now' : 'all'], $over);
    return 'wrong.php?' . http_build_query(array_filter($q, fn($v) => $v !== null && $v !== ''));
}

$pageTitle = $cert['short_name'] . ' 오답노트';
$active = 'wrong';
$page = 'result';
require __DIR__ . '/includes/header.php';
?>
<section class="card glass">
    <h1><?= h($cert['short_name']) ?> 오답노트 <small class="muted"><?= count($rows) ?>문제</small></h1>
    <div class="filters">
        <a class="chip <?= $onlyNow ? 'on' : '' ?>" href="<?= h(wrongUrl(['view' => 'now'])) ?>">아직 못 맞힌 문제</a>
        <a class="chip <?= !$onlyNow ? 'on' : '' ?>" href="<?= h(wrongUrl(['view' => 'all'])) ?>">한 번이라도 틀린 문제</a>
        <span class="sep"></span>
        <a class="chip <?= !$subject ? 'on' : '' ?>" href="<?= h(wrongUrl(['subject' => null])) ?>">전체 과목</a>
        <?php foreach ($cert['subjects'] as $no => $s): ?>
            <a class="chip <?= $subject === $no ? 'on' : '' ?>" href="<?= h(wrongUrl(['subject' => $no])) ?>" title="<?= h($s['name']) ?>"><?= $no ?>과목</a>
        <?php endforeach; ?>
    </div>
    <?php if ($topicList): ?>
        <div class="filters">
            <a class="chip <?= $topic === '' ? 'on' : '' ?>" href="<?= h(wrongUrl(['topic' => null])) ?>">모든 단원</a>
            <?php foreach ($topicList as $t): ?>
                <a class="chip <?= $topic === $t['topic'] ? 'on' : '' ?>" href="<?= h(wrongUrl(['topic' => $t['topic']])) ?>"><?= h($t['topic']) ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <p class="muted small-note">'아직 못 맞힌 문제'는 가장 최근에 풀었을 때 틀린 문제야. 다음에 맞히면 목록에서 빠져. 틀린 문제는 맞춤 출제에서 다시 섞여 나와.</p>
    <?php if ($topic !== ''): ?><p class="small-note"><a class="btn small" href="start.php?topic=<?= urlencode($topic) ?>">'<?= h($topic) ?>' 단원만 다시 풀기</a></p><?php endif; ?>
</section>

<?php if (!$rows): ?>
    <section class="card glass"><p class="muted">보여줄 오답이 없어. 시험을 풀거나, 필터를 바꿔봐.</p></section>
<?php endif; ?>

<?php foreach ($rows as $r):
    $choices = json_decode($r['choices'], true);
    $ans = (int)$r['answer'];
    $pick = $r['last_ans'] === null ? null : (int)$r['last_ans']; ?>
    <article class="card glass review wrong">
        <div class="qmeta">
            <?= subjTag($cert, (int)$r['subject']) ?>
            <span class="tag topic"><?= h($r['topic']) ?></span>
            <?= stars((int)$r['difficulty']) ?>
            <span class="tag"><?= (int)$r['wrong_n'] ?>번 틀림 / <?= (int)$r['tries'] ?>번 풂</span>
            <span class="muted small-note">마지막 <?= h($r['last_date']) ?></span>
        </div>
        <p class="qtext"><?= nl2br(h($r['question'])) ?></p>
        <?php if (!empty($r['code_block'])): ?><pre class="sql"><code><?= h($r['code_block']) ?></code></pre><?php endif; ?>
        <ul class="opts <?= (int)$r['mono'] ? 'mono' : '' ?>">
            <?php foreach ($choices as $n => $text): $n++; ?>
                <li class="<?= $n === $ans ? 'is-answer' : '' ?> <?= $n === $pick && $n !== $ans ? 'is-mine-wrong' : '' ?>">
                    <span class="cnum"><?= CIRCLED[$n] ?></span><span class="ctext"><?= h($text) ?></span>
                    <?php if ($n === $ans): ?><em>정답</em><?php endif; ?>
                    <?php if ($n === $pick): ?><em class="mine">마지막 내 선택</em><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <div class="explain"><b>해설</b><p><?= nl2br(h($r['explanation'])) ?></p></div>
    </article>
<?php endforeach; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
