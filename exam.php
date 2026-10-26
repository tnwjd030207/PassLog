<?php
// 시간 제한 시험 화면 (실전 모의고사 / 시간제한 연습): 한 문제씩, 문제별 타이머, 일시정지
declare(strict_types=1);
define('GUEST_OK', true);
require __DIR__ . '/includes/functions.php';

$test = currentTest();
if (!$test) redirect('start.php');
if ((int)$test['q_seconds'] <= 0) redirect('test.php');
$cert = certById((int)$test['cert_id']);
$_SESSION['cert'] = $cert['code'];   // 머리말의 자격증 표시를 이 시험에 맞춤
$isExam = $test['kind'] === 'exam';
$subjStarts = [];   // 과목이 바뀌는 문제 번호
foreach (qall('SELECT ti.position, q.subject FROM test_items ti JOIN questions q ON q.id = ti.question_id WHERE ti.test_id = ? ORDER BY ti.position', [(int)$test['id']]) as $r) {
    $subjStarts[(int)$r['subject']] ??= (int)$r['position'];
}

$pageTitle = $cert['short_name'] . ' ' . kindLabel($test);
$active = 'test';
$page = 'exam';
require __DIR__ . '/includes/header.php';
?>
<div id="timed" data-test="<?= (int)$test['id'] ?>" data-limit="<?= (int)$test['q_seconds'] ?>" data-exam="<?= $isExam ? 1 : 0 ?>"
     data-subjects="<?= h(json_encode(array_map(fn($s) => $s['name'], $cert['subjects']), JSON_UNESCAPED_UNICODE)) ?>">
    <div class="exam-head card glass">
        <div class="exam-title">
            <b><?= h($cert['short_name']) ?> <?= h(kindLabel($test)) ?></b>
            <span class="muted">문제당 <?= (int)$test['q_seconds'] ?>초 · <?= $isExam ? '힌트 없음 · ' : '' ?>넘긴 문제는 다시 못 봐</span>
        </div>
        <div class="tprog">
            <div class="tprog-top"><b id="t-pos">-</b><span class="muted"> / <?= (int)$test['total_cnt'] ?></span>
                <span class="muted small" id="t-subj"></span></div>
            <div class="track big"><i id="t-bar" style="--w:0%"></i>
                <?php foreach ($subjStarts as $no => $p): if ($p <= 1) continue; ?>
                    <span class="tick" style="left:<?= round(($p - 1) / (int)$test['total_cnt'] * 100, 2) ?>%" title="<?= $no ?>과목 시작"></span>
                <?php endforeach; ?>
            </div>
            <div class="muted small">일시정지 <b id="t-pc">0</b>회 · <b id="t-pt">0초</b></div>
        </div>
        <div class="timer-ring" title="이 문제 남은 시간">
            <canvas id="ring" width="84" height="84"></canvas>
            <span id="remain">--</span>
        </div>
        <button type="button" class="btn pause-btn" id="pause" aria-pressed="false">일시정지</button>
    </div>

    <section class="card qcard glass" id="qcard" aria-live="polite">
        <p class="muted">불러오는 중…</p>
    </section>

    <div class="paused-veil" id="veil" hidden>
        <div class="card glass">
            <h2>일시정지 중</h2>
            <p class="muted">시계가 멈췄어. 문제는 다시 시작하면 보여.</p>
            <p>지금까지 일시정지 <b id="v-pc">0</b>회 · 총 <b id="v-pt">0초</b></p>
            <button type="button" class="btn primary glow" id="resume">다시 시작</button>
        </div>
    </div>

    <div class="exam-nav">
        <button type="button" class="btn" id="skip">넘기기</button>
        <span class="muted small" id="t-hint-note"></span>
        <button type="button" class="btn primary" id="next" disabled>답 제출 · 다음 →</button>
    </div>
    <p class="muted keys">키보드: 1~4 답 선택 · Enter 제출 · P 일시정지/다시 시작<?= $isExam ? '' : ' · H 힌트' ?></p>
    <form method="post" action="start.php" class="quit-form" onsubmit="return confirm('이 시험을 그만두면 기록이 지워져. 그만둘까?');">
        <input type="hidden" name="action" value="abandon"><input type="hidden" name="test_id" value="<?= (int)$test['id'] ?>">
        <button class="linklike" type="submit">이 시험 그만두기</button>
    </form>
</div>
<div class="toast" id="toast" hidden></div>
<script src="assets/exam.js?v=3"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
