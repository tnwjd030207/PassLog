<?php
// 시간 제한 없는 연습 화면 (자유롭게 앞뒤 이동, 힌트 가능)
declare(strict_types=1);
define('GUEST_OK', true);
require __DIR__ . '/includes/functions.php';

$test = currentTest();
if (!$test) redirect('start.php');
if ((int)$test['q_seconds'] > 0) redirect('exam.php');
$cert = certById((int)$test['cert_id']);
$_SESSION['cert'] = $cert['code'];   // 머리말의 자격증 표시를 이 시험에 맞춤

// 시험지를 처음 연 순간부터 시간을 잰다
q("UPDATE tests SET started_at = NOW()
    WHERE id = ? AND status = 'in_progress'
      AND NOT EXISTS (SELECT 1 FROM test_items WHERE test_id = ? AND (user_answer IS NOT NULL OR hint_used = 1))",
  [(int)$test['id'], (int)$test['id']]);
$test = loadTestRaw((int)$test['id']);

// 정답/해설은 가져오지 않는다. 힌트는 이미 눌렀던 문제만.
$items = qall('SELECT ti.id AS item_id, ti.position, ti.user_answer, ti.hint_used,
                      q.subject, q.topic, q.difficulty, q.question, q.code_block, q.choices, q.mono,
                      CASE WHEN ti.hint_used = 1 THEN q.hint ELSE NULL END AS hint_text
                 FROM test_items ti JOIN questions q ON q.id = ti.question_id
                WHERE ti.test_id = ? ORDER BY ti.position', [(int)$test['id']]);
$isExam = $test['kind'] === 'exam';   // (예전 90분 모의고사 기록 호환)

$pageTitle = $cert['short_name'] . ' ' . kindLabel($test);
$active = 'test';
$page = 'exam';
require __DIR__ . '/includes/header.php';
$start = strtotime($test['started_at']);
$prevSubj = null;
?>
<div id="exam" data-test="<?= (int)$test['id'] ?>" data-start="<?= $start ?>" data-total="<?= count($items) ?>" data-kind="practice" data-left="0" data-limit="0">
    <div class="exam-head card glass">
        <div class="exam-title">
            <b><?= h($cert['short_name']) ?> <?= h(kindLabel($test)) ?></b>
            <span class="muted"><?= $test['difficulty'] ? '난이도 ' . h(DIFF_LABEL[(int)$test['difficulty']]) : '난이도 혼합' ?><?= $test['topic_filter'] ? ' · ' . h($test['topic_filter']) : '' ?></span>
        </div>
        <div class="dots" id="dots">
            <?php foreach ($items as $i => $it): $gap = $prevSubj !== null && $prevSubj !== (int)$it['subject']; $prevSubj = (int)$it['subject']; ?>
                <button type="button" class="dot <?= $it['user_answer'] !== null ? 'done' : '' ?> <?= $gap ? 'gap-before' : '' ?>" data-go="<?= $i ?>"
                        aria-label="<?= (int)$it['position'] ?>번 문제"><?= (int)$it['position'] ?></button>
            <?php endforeach; ?>
        </div>
        <div class="timer" title="시작한 지 얼마나 됐는지">경과 <span id="elapsed">00:00</span></div>
    </div>

    <?php foreach ($items as $i => $it): $choices = json_decode($it['choices'], true); ?>
        <section class="card qcard glass" data-idx="<?= $i ?>" data-item="<?= (int)$it['item_id'] ?>" <?= $i === 0 ? '' : 'hidden' ?>>
            <div class="qmeta">
                <span class="qno">Q<?= (int)$it['position'] ?></span>
                <?= subjTag($cert, (int)$it['subject']) ?>
                <span class="tag topic"><?= h($it['topic']) ?></span>
                <?= stars((int)$it['difficulty']) ?>
            </div>
            <p class="qtext"><?= nl2br(h($it['question'])) ?></p>
            <?php if (!empty($it['code_block'])): ?><pre class="sql"><code><?= h($it['code_block']) ?></code></pre><?php endif; ?>
            <div class="choices <?= (int)$it['mono'] ? 'mono' : '' ?>" role="radiogroup">
                <?php foreach ($choices as $n => $text): $n++; ?>
                    <label class="choice">
                        <input type="radio" name="q<?= (int)$it['item_id'] ?>" value="<?= $n ?>" <?= (int)$it['user_answer'] === $n ? 'checked' : '' ?>>
                        <span class="cnum"><?= CIRCLED[$n] ?></span><span class="ctext"><?= h($text) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <?php if (!$isExam): ?>
            <div class="hint-area">
                <button type="button" class="btn hint-btn" <?= $it['hint_used'] ? 'hidden' : '' ?>>힌트 보기</button>
                <div class="hint-box" <?= $it['hint_used'] ? '' : 'hidden' ?>><b>힌트</b><p><?= h($it['hint_text'] ?? '') ?></p></div>
            </div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>

    <div class="exam-nav">
        <button type="button" class="btn" id="prev">← 이전</button>
        <span class="muted" id="progress">1 / <?= count($items) ?></span>
        <button type="button" class="btn primary" id="next">다음 →</button>
        <button type="button" class="btn ok-btn" id="submit" hidden>제출하고 채점하기</button>
    </div>
    <p class="muted keys">키보드: 1~4 답 선택 · ← → 이동 · H 힌트</p>
    <form method="post" action="start.php" class="quit-form" onsubmit="return confirm('이 시험을 그만두면 기록이 지워져. 그만둘까?');">
        <input type="hidden" name="action" value="abandon"><input type="hidden" name="test_id" value="<?= (int)$test['id'] ?>">
        <button class="linklike" type="submit">이 시험 그만두기</button>
    </form>
</div>

<dialog id="confirm">
    <form method="dialog">
        <h3>제출할까?</h3>
        <p id="confirm-msg"></p>
        <div class="dialog-btns"><button class="btn" value="cancel">더 풀어볼래</button><button class="btn primary" value="ok" id="confirm-ok">제출</button></div>
    </form>
</dialog>
<div class="toast" id="toast" hidden></div>
<script src="assets/test.js?v=3"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
